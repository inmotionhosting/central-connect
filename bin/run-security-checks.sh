#!/usr/bin/env bash
# Run the Central Connect Semgrep / PHPCS / Conftest checks, including the
# fixture self-tests that prove each rule and policy still fires.
#
# Usage: bin/run-security-checks.sh [semgrep|phpcs|conftest ...]
#   With no arguments every check runs. A missing tool is skipped locally and
#   is a failure when CI is set (GitHub Actions sets CI=true).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

SEMGREP_RULES="rules/central-connect-semgrep.yml"
CONFTEST_POLICY="policies/central-connect"
FIXTURES="ci/testdata"

fail=0

missing_tool() {
	if [[ -n "${CI:-}" ]]; then
		echo "FAIL $1 is not installed"
		fail=1
	else
		echo "SKIP $1 (not installed)"
	fi
}

run_semgrep() {
	if ! command -v semgrep >/dev/null 2>&1; then
		missing_tool semgrep
		return 0
	fi
	local args=(--config "${SEMGREP_RULES}" --metrics=off --disable-version-check --quiet)

	echo "== semgrep (plugin sources) =="
	semgrep "${args[@]}" --error \
		--exclude=vendor --exclude=node_modules --exclude=build --exclude=tests --exclude=ci . || fail=1

	echo "== semgrep fixtures (each bad/<rule-id>.php must trigger <rule-id>) =="
	local report
	report="$(semgrep "${args[@]}" --json "${FIXTURES}/semgrep/bad" || true)"
	for fixture in "${FIXTURES}"/semgrep/bad/*.php; do
		local rule
		rule="$(basename "${fixture}" .php)"
		if php -r '
			$report = json_decode( $argv[1], true );
			foreach ( $report["results"] ?? array() as $result ) {
				if ( basename( $result["path"] ) === $argv[2] . ".php" && substr( $result["check_id"], -strlen( $argv[2] ) ) === $argv[2] ) {
					exit( 0 );
				}
			}
			exit( 1 );
		' "${report}" "${rule}"; then
			echo "OK: ${rule} flagged"
		else
			echo "FAIL: ${rule} did not flag ${fixture}"
			fail=1
		fi
	done
	local rule_count fixture_count
	rule_count="$(grep -c '^  - id: ' "${SEMGREP_RULES}")"
	fixture_count="$(find "${FIXTURES}/semgrep/bad" -name '*.php' | wc -l | tr -d ' ')"
	if [[ "${rule_count}" -ne "${fixture_count}" ]]; then
		echo "FAIL: ${rule_count} semgrep rules but ${fixture_count} bad fixtures; add one fixture per rule"
		fail=1
	fi

	echo "== semgrep fixtures (good/ must be clean) =="
	semgrep "${args[@]}" --error "${FIXTURES}/semgrep/good" || fail=1
}

run_phpcs() {
	if [[ ! -x vendor/bin/phpcs ]]; then
		missing_tool "vendor/bin/phpcs (run composer install)"
		return 0
	fi
	echo "== phpcs (phpcs.xml: WordPress + PHPCompatibilityWP 7.4-) =="
	vendor/bin/phpcs -q --no-colors || fail=1
}

run_conftest() {
	if ! command -v conftest >/dev/null 2>&1; then
		missing_tool conftest
		return 0
	fi
	local args=(--no-color --policy "${CONFTEST_POLICY}")

	echo "== conftest release metadata =="
	php bin/plugin-meta.php | conftest test "${args[@]}" --namespace release --parser json - || fail=1

	echo "== conftest composer.json =="
	conftest test "${args[@]}" --namespace composer composer.json || fail=1

	echo "== conftest GitHub workflows =="
	local workflows=()
	shopt -s nullglob
	workflows=(.github/workflows/*.yml .github/workflows/*.yaml)
	shopt -u nullglob
	if [[ ${#workflows[@]} -gt 0 ]]; then
		conftest test "${args[@]}" --namespace workflows "${workflows[@]}" || fail=1
	else
		echo "SKIP no workflow files"
	fi

	echo "== conftest fixtures =="
	local ns fixture
	for ns in release composer workflows; do
		for fixture in "${FIXTURES}/conftest/${ns}"/good.*; do
			conftest test "${args[@]}" --namespace "${ns}" "${fixture}" >/dev/null || {
				echo "FAIL: expected ${fixture} to pass"
				fail=1
			}
		done
		for fixture in "${FIXTURES}/conftest/${ns}"/bad-*; do
			if conftest test "${args[@]}" --namespace "${ns}" "${fixture}" >/dev/null 2>&1; then
				echo "FAIL: expected deny for ${fixture}"
				fail=1
			else
				echo "OK: denied ${ns}/$(basename "${fixture}")"
			fi
		done
	done
}

checks=("$@")
if [[ ${#checks[@]} -eq 0 ]]; then
	checks=(semgrep phpcs conftest)
fi

for check in "${checks[@]}"; do
	case "${check}" in
		semgrep) run_semgrep ;;
		phpcs) run_phpcs ;;
		conftest) run_conftest ;;
		*)
			echo "Unknown check: ${check}" >&2
			exit 2
			;;
	esac
done

if [[ "${fail}" -ne 0 ]]; then
	echo "security checks FAILED"
	exit 1
fi
echo "security checks PASSED"
