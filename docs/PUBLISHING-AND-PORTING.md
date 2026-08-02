# Publishing & Multi-Language Porting Strategy

Analysis of `monovm/whois-php` and a concrete plan for (a) where else this can be
published and (b) how to ship the same capability in other programming languages
without maintaining six divergent copies of the detection logic.

---

## 1. What this package actually is

| | |
|---|---|
| Package | `monovm/whois-php` |
| Registry | Packagist — published, 31 releases, latest `v1.3.11` (2026-06-07) |
| License | MIT |
| Runtime deps | none (only `ext-json`, plus `ext-curl` in practice) |
| Code size | ~1,280 LOC PHP + a 1,429-line data file |

### Architecture

```
Checker            batch/convenience API -> "available" | "unavailable" | "premium" | "invalid"
WhoisHandler       single-domain API -> isAvailable / isValid / getWhoisMessage / getTld / getSld
Whois              transport + registry table (TCP :43 and HTTP/RDAP), extends into the two above
AvailabilityDetector   823 lines of heuristics that turn free-text WHOIS into a boolean
dist.whois.json    285 server groups covering 890 TLD extensions
```

### Where the value actually sits

This is the single most important observation for the porting question.

The transport is trivial — opening a TCP socket on port 43 or doing an HTTP GET is
20 lines in any language. **Roughly 95% of the value of this package is data, not code:**

1. **`dist.whois.json`** — 890 TLD extensions mapped to a WHOIS/RDAP endpoint plus the
   registry's "not found" marker string. This is curated, hard-won, and constantly rots
   as registries move servers. Look at the recent commit history: `.pro` removed, `.info`
   moved to RDAP, `.sx` restriction notices, `.de` availability — every one of those is a
   data fix, not a logic fix.
2. **`AvailabilityDetector`** — ~40 availability keywords, dozens of unavailability
   regexes, per-TLD pattern tables, registration-indicator scoring, RDAP-response
   detection. Also data expressed as code.

**A port that re-implements the code but forks the data is worthless within six months.**
Section 4 below is built around that constraint.

---

## 2. Fix these before promoting the package anywhere

Publishing more widely amplifies existing defects. These are worth closing first.

### 2.1 `str_starts_with()` breaks the declared PHP floor — highest priority

`composer.json` declares `"php": ">=7.4"`, and CI pins PHP 7.4. But
`src/AvailabilityDetector.php` calls `str_starts_with()` at lines 152-156 and 512-516,
which is **PHP 8.0+**. On a real PHP 7.4 install, any call into the detector raises
`Error: Call to undefined function str_starts_with()`.

Fix — pick one:

```jsonc
// Option A (recommended): the code is already PHP 8 code, say so
"require": { "php": ">=8.0", "ext-json": "*", "ext-curl": "*" }

// Option B: keep 7.4 support
"require": { "php": ">=7.4", "ext-json": "*", "ext-curl": "*",
             "symfony/polyfill-php80": "^1.28" }
```

### 2.2 `ext-curl` is an undeclared dependency

`Whois::httpWhoisLookup()` uses `curl_init()`. 51 of the 285 server groups are HTTP/RDAP,
so on a curl-less PHP build those TLDs fail at runtime with no install-time warning.
Add `"ext-curl": "*"` to `require`.

### 2.3 CI does not test the versions the package claims to support

The workflow builds on PHP 7.4 only. Run a matrix (`7.4`/`8.0`/`8.1`/`8.2`/`8.3`/`8.4`)
so 2.1-class defects surface. Also: `phpcbf` (auto-fix) runs immediately before `phpcs`
(check), which makes the lint gate unable to fail. Drop the `phpcbf` step from CI.

### 2.4 Network-dependent tests

`CheckerTest` and `IntegrationTest` hit live registries. That means CI red-lines on
registry rate limits rather than on real regressions, and contributors can't run tests
offline. Split into:

- **unit** — `AvailabilityDetector` against recorded fixtures, no network, runs on every PR
- **integration** — live lookups, nightly cron only

This split is also the foundation of the cross-language conformance suite (§4.2).

### 2.5 Two detection bugs the fixtures should pin down

- **`.uk` false negative.** `containsUnavailabilityIndicators()` maps `.uk` to
  `/registered/i`, evaluated at PRIORITY 2 — before any availability keyword check.
  A registry response reading `This domain name has not been registered` contains the
  substring `registered`, so an *available* `.uk` domain is reported as unavailable.
  These patterns need negative lookbehind or word-boundary anchoring.
- **Bare `'free'` keyword.** In the availability keyword list, matched with `strpos()`
  (substring, not word). It matches `Freenom`, `Freeparking`, `toll-free`, `free of charge`.
  Anchor to `\bfree\b` or to the `status: free` form that was actually intended.

### 2.6 Packaging hygiene

- **No `.gitattributes`** — every `composer require` downloads `tests/`, `.github/`,
  `phpunit.xml`, `composer.lock`. Add:
  ```
  /tests            export-ignore
  /.github          export-ignore
  /phpunit.xml      export-ignore
  /composer.lock    export-ignore
  /docs             export-ignore
  ```
- **Thin metadata** — keywords are only `["whois","php"]`. Packagist search is
  keyword-driven; add `domain`, `dns`, `rdap`, `domain-availability`, `domain-checker`,
  `tld`, `registrar`, `domain-search`. Add `homepage` and a `support` block
  (`issues`, `source`, `email`).
- **No CHANGELOG.** 31 releases with no changelog makes upgrades a gamble for consumers.

---

## 3. The strategic decision: RDAP-first

ICANN has retired the port-43 WHOIS requirement for gTLDs in favour of RDAP, and
registries are switching off legacy WHOIS on their own schedules. The repo is already
drifting that way one TLD at a time (`fix: switch .info and related TLDs from deprecated
WHOIS to RDAP`).

Doing this deliberately rather than reactively changes the economics of everything below:

- RDAP returns **structured JSON** with a defined status code — `404` means not
  registered. That is a *fact*, not a heuristic. For every gTLD it replaces the entire
  823-line guessing engine with one status-code check.
- The `AvailabilityDetector` heuristics stay, but shrink to what they are actually good
  at: ccTLDs that still only speak port-43 free-text WHOIS.
- **Ports become dramatically cheaper.** Porting "HTTP GET + check for 404" to six
  languages is a weekend. Porting 823 lines of locale-specific regex heuristics to six
  languages, and keeping them in sync forever, is not a project anyone finishes.

**Recommendation: resolve endpoints from the IANA RDAP bootstrap registry
(`https://data.iana.org/rdap/dns.json`), fall back to `dist.whois.json` port-43 entries
only when a TLD has no RDAP service.** Do this *before* starting any port, not after.

---

## 4. Multi-language strategy

### 4.1 Do not hand-port the detector six times

The naive approach — "rewrite `AvailabilityDetector.php` in TypeScript, then Python, then
Go" — produces six codebases that disagree with each other within two release cycles,
because every TLD fix lands in one of them. Avoid it.

### 4.2 Extract a language-neutral core first

Create a separate repo, `monovm/whois-data`, containing **no executable code**:

```
whois-data/
  servers.json          # today's dist.whois.json, schema-versioned
  rules.json            # the detector's tables, extracted as data:
                        #   availability_keywords[], unavailability_indicators[],
                        #   registration_indicators[], no_match_patterns[],
                        #   tld_specific[], rdap_registered_keys[]
  schema/               # JSON Schema for both files — CI-validated
  fixtures/             # THE critical asset
    com/registered.txt        + expected.json
    com/available.txt         + expected.json
    uk/not-registered.txt     + expected.json
    de/status-connect.txt     + expected.json
    ...                       (one per TLD quirk ever fixed in this repo's history)
  CONFORMANCE.md        # what a compliant implementation must do
```

Every fixture is a recorded real registry response plus the verdict it must produce.
Mine them out of this repo's git history — each of those `fix:` commits is a test case
that was never written down.

Each language port then reduces to:

```
transport (~80 LOC)  +  rule interpreter (~250 LOC)  +  idiomatic public API (~100 LOC)
```

and its CI runs the shared fixture suite. A TLD fix lands once, in `whois-data`; every
port picks it up on its next data bump. This is the only version of the multi-language
plan that is maintainable by a small team.

Ship the data as packages in its own right — `monovm/whois-data` (Packagist),
`@monovm/whois-data` (npm), `monovm-whois-data` (PyPI) — so a moved registry server is a
*data* release, not a code release in six languages.

### 4.3 Recommended port order

Names verified available on their registries as of this writing.

| # | Language | Package name | Registry | Effort | Why this order |
|---|----------|--------------|----------|--------|----------------|
| 1 | TypeScript / Node | `@monovm/whois` | npm | Low | Largest developer audience by a wide margin; `node:net` for port 43, `fetch` for RDAP; ships types. |
| 2 | Python | `monovm-whois` | PyPI | Low | Owns the infra/security/automation tooling space; stdlib `socket` + `httpx`. Sync + async API. |
| 3 | Go | `github.com/monovm/whois-go` | pkg.go.dev (tag only) | Low | No registry ceremony — publishing is a git tag. `go:embed` the data, and a single static `whois` binary is a genuinely compelling standalone product. |
| 4 | C# / .NET | `MonoVM.Whois` | NuGet | Medium | Registrar, billing, and hosting control-panel software is disproportionately .NET — commercially the closest audience to MonoVM's own. |
| 5 | Rust | `monovm-whois` | crates.io | Medium | Smaller audience than the above, but yields a fast, dependency-light CLI and clean FFI. |
| 6 | Ruby | `monovm-whois` | RubyGems | Low | Cheap to build, but the incumbent `whois` gem is entrenched. Only worth it on demand. |
| 7 | Java / Kotlin | `com.monovm:whois` | Maven Central | High | Highest publishing overhead (namespace verification, GPG signing, staging repo). Do last, and only if enterprise demand appears. |

Note for the npm port: **browsers cannot open raw TCP sockets.** Ship it Node-only, or
expose an RDAP-only subset for browser builds (RDAP is plain HTTPS, so it works — modulo
CORS, which most RDAP servers do permit).

### 4.4 Keep the API shape recognisable, not identical

Same concepts everywhere — `whois(domain)`, `isAvailable`, `isValid`, `tld`, `sld`, raw
message — but idiomatic per language: `async/await` in TS, `snake_case` + context managers
in Python, `(result, error)` in Go, `Result<T, E>` in Rust, `Task<T>` in .NET. Do not
transliterate PHP's static-factory (`WhoisHandler::whois()`) style into languages where
it reads as foreign.

---

## 5. Distribution surfaces beyond package registries

Packagist is the only meaningful PHP registry, so growth for the PHP package comes from
being present where domain lookups are actually performed:

- **Laravel package** (`monovm/laravel-whois`) — service provider, config file, facade,
  `php artisan whois:check` command, cache layer. Laravel is where most new PHP work lives.
- **WordPress plugin** (wordpress.org/plugins) — a domain-search shortcode and block.
  Enormous audience, and hosting/registrar sites running WordPress are exactly the target user.
- **WHMCS addon module** — this library descends from WHMCS's own WHOIS class; the WHMCS
  Marketplace is a direct, high-intent channel for registrars.
- **Docker image + REST microservice** (GHCR / Docker Hub) — `GET /whois/example.com`
  returning JSON. This is the single highest-leverage item on the list: it makes the
  library usable from *every* language today, and doubles as the reference implementation
  the ports are validated against. Consider building this before writing any port.
- **GitHub Action** (`monovm/whois-action`) — domain-expiry monitoring in CI. Cheap to
  build, and a steady source of inbound awareness.
- **CLI binary** — falls out of the Go port for free; distribute via Homebrew tap,
  `go install`, and GitHub Releases.

---

## 6. Suggested sequencing

**Phase 1 — stabilise (do this regardless of everything else)**
Fix the PHP-version/`ext-curl` declarations (§2.1, §2.2), fix the two detection bugs
(§2.5), add `.gitattributes` and richer metadata (§2.6), split unit from integration
tests and add a PHP version matrix (§2.3, §2.4).

**Phase 2 — restructure**
Adopt RDAP-first with IANA bootstrap (§3). Extract `monovm/whois-data` with schema and
fixtures mined from git history (§4.2). Refactor the PHP package to consume it — proving
the extraction works *before* any second language depends on it.

**Phase 3 — reach, cheaply**
Ship the Docker/REST service and the Laravel package. Both reuse the PHP code as-is and
serve non-PHP consumers immediately, which also tells you which languages people actually
want by looking at who calls the API.

**Phase 4 — port**
TypeScript, then Python, then Go, each validated against the shared conformance fixtures.
Reassess .NET / Rust / Ruby / Java based on demand signals from Phase 3.

---

## 7. Localization of detection strings

Distinct from programming-language ports, and worth separating explicitly since the two
get conflated.

`AvailabilityDetector` already carries partial Spanish coverage
(`no se encontro el objeto`, `el dominio no se encuentra registrado`,
`no está registrado`) mixed into an otherwise English keyword list. ccTLD registries
answer in their own languages — German, French, Japanese, Korean, Russian, Chinese and
others — and those are currently unhandled, which is a likely source of wrong verdicts on
exactly the ccTLDs that RDAP will *not* rescue.

When extracting `rules.json` (§4.2), key the availability/unavailability keyword tables
by language, and record which registries respond in which language. That turns
"add Japanese support" into a data contribution any user can make via PR, instead of a
code change in seven repositories.

Also note: these responses are not reliably UTF-8 (several registries emit Latin-1 or
Shift-JIS on port 43). Every port needs an explicit decoding step — PHP's byte-string
tolerance hides this today, but Python, Go, Rust and .NET will not be so forgiving.
