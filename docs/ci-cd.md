# CI/CD

Beacon requires Chrome and ChromeDriver at runtime. This page covers how to set up your pipeline so both are available when your tests run.

ChromeDriver is installed automatically by `composer install` via `composer run driver:install`. The only thing you need to ensure is that Chrome itself is present on the runner.

## Key environment variables

| Variable | Purpose |
|---|---|
| `PANTHER_NO_SANDBOX` | Set to `1` — required on Linux CI runners where Chrome can't use the sandbox |
| `CHROME_PATH` | Override the Chrome binary path if it's in a non-standard location |

---

## GitHub Actions

Chrome is pre-installed on `ubuntu-latest`, `macos-latest`, and `windows-latest` GitHub-hosted runners, so the minimal setup is just `composer install`.

```yaml
name: Tests

on: [push, pull_request]

permissions:
  contents: read

jobs:
  test:
    runs-on: ubuntu-latest
    env:
      PANTHER_NO_SANDBOX: 1
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: curl, dom, json, mbstring, xml, zip

      - run: composer install --prefer-dist --no-progress --no-interaction

      - run: vendor/bin/phpunit --testdox --no-coverage
```

### With Lighthouse tests

If your tests use Lighthouse, install Node.js and the Lighthouse CLI before running:

```yaml
      - uses: actions/setup-node@v4
        with:
          node-version: '20'

      - run: composer run lighthouse:install

      - run: vendor/bin/phpunit --testdox --no-coverage
```

### Matrix across OS

```yaml
  test:
    runs-on: ${{ matrix.os }}
    strategy:
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: ['8.5']
    env:
      PANTHER_NO_SANDBOX: 1
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: curl, dom, json, mbstring, xml, zip

      - run: composer install --prefer-dist --no-progress --no-interaction

      - run: vendor/bin/phpunit --testdox --no-coverage
```

---

## GitLab CI

GitLab shared runners don't include Chrome by default. Install it as part of your job, or use a Docker image that already has it.

### Using a Chrome Docker image

```yaml
test:
  image: zenika/alpine-chrome:with-node
  variables:
    PANTHER_NO_SANDBOX: "1"
  script:
    - apk add --no-cache php83 php83-curl php83-dom php83-json php83-mbstring php83-xml php83-zip composer
    - composer install --prefer-dist --no-progress --no-interaction
    - vendor/bin/phpunit --testdox --no-coverage
```

### Installing Chrome manually

```yaml
test:
  image: ubuntu:24.04
  variables:
    PANTHER_NO_SANDBOX: "1"
  before_script:
    - apt-get update -qq
    - apt-get install -y google-chrome-stable php8.5-cli php8.5-curl php8.5-dom php8.5-mbstring php8.5-xml php8.5-zip composer
  script:
    - composer install --prefer-dist --no-progress --no-interaction
    - vendor/bin/phpunit --testdox --no-coverage
```

### With Lighthouse

```yaml
test:
  image: zenika/alpine-chrome:with-node
  variables:
    PANTHER_NO_SANDBOX: "1"
  script:
    - composer install --prefer-dist --no-progress --no-interaction
    - composer run lighthouse:install
    - vendor/bin/phpunit --testdox --no-coverage
```

---

## Bitbucket Pipelines

```yaml
pipelines:
  default:
    - step:
        name: Test
        image: php:8.5-cli
        caches:
          - composer
        script:
          - apt-get update -qq && apt-get install -y google-chrome-stable unzip
          - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
          - composer install --prefer-dist --no-progress --no-interaction
          - PANTHER_NO_SANDBOX=1 vendor/bin/phpunit --testdox --no-coverage
```

---

## CircleCI

```yaml
version: 2.1

jobs:
  test:
    docker:
      - image: cimg/php:8.5-browsers  # includes Chrome
    environment:
      PANTHER_NO_SANDBOX: "1"
    steps:
      - checkout
      - run: composer install --prefer-dist --no-progress --no-interaction
      - run: vendor/bin/phpunit --testdox --no-coverage
```

---

## Tips

**Chrome sandbox** — always set `PANTHER_NO_SANDBOX=1` (or `PANTHER_NO_SANDBOX: "1"` in YAML) on Linux CI runners. Chrome's sandbox requires kernel namespacing that most container environments don't allow.

**ChromeDriver version** — `composer install` runs `driver:install` automatically, which detects the Chrome version on the runner and downloads the matching ChromeDriver. If Chrome is upgraded mid-pipeline (e.g. via `apt-get upgrade`), run `composer run driver:update` afterwards.

**Caching** — cache the `vendor/` directory between runs as normal. The `drivers/` directory can also be cached, but since `driver:install` skips the download when the version already matches, the time saving is minimal.

**Splitting unit and integration tests** — unit tests don't need Chrome at all. If you want faster feedback, run them in a separate job without the Chrome setup step:

```yaml
  unit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: curl, dom, json, mbstring, xml, zip
      # Skip driver:install — no Chrome needed for unit tests
      - run: composer install --prefer-dist --no-progress --no-interaction --no-scripts
      - run: vendor/bin/phpunit --testdox --no-coverage --exclude-group=integration

  integration:
    runs-on: ubuntu-latest
    env:
      PANTHER_NO_SANDBOX: 1
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: curl, dom, json, mbstring, xml, zip
      - run: composer install --prefer-dist --no-progress --no-interaction
      - run: vendor/bin/phpunit --testdox --no-coverage --group=integration
```
