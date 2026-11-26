#!/usr/bin/env bash
# shellcheck disable=SC2016 # Single-quoted `php -r` snippets are PHP, not shell expansions.
# Build the Central Connect release package and publish it to WordPress.org SVN.
#
# Manual only; never called from CI. Modelled on BoldGrid post-and-page-builder
# bin/deploy.sh (tag -> trunk promotion) plus its bin/package.sh build checks.
#
# The package is exported with `git archive` from a committed ref, then
# filtered through .distignore, so untracked files, vendor/ and development
# tooling never reach SVN.
#
# Usage:
#   WP_USERNAME=... WP_PASSWORD=... bin/deploy.sh [options] [version]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="central-connect"
PLUGIN_MAIN="${PLUGIN_SLUG}.php"
WP_SVN_REPO="${WP_SVN_REPO:-https://plugins.svn.wordpress.org/${PLUGIN_SLUG}}"
WP_API_URL="https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=${PLUGIN_SLUG}"
BUILD_ROOT="${PLUGIN_ROOT}/build"
PACKAGE_DIR="${BUILD_ROOT}/${PLUGIN_SLUG}"
ZIP_FILE="${BUILD_ROOT}/${PLUGIN_SLUG}.zip"
SVN_DIR="${BUILD_ROOT}/svn"

MODE="release"
DRY_RUN=0
AUTO_YES=0
ALLOW_DIRTY=0
CHECK_API=1
STRICT_API=0
GIT_REF="HEAD"
VERSION=""

usage() {
	cat <<EOF
Build the ${PLUGIN_SLUG} package and publish it to WordPress.org SVN.

Usage:
  WP_USERNAME=... WP_PASSWORD=... $0 [options] [version]

Arguments:
  version           X.Y.Z. Defaults to the Version header of ${PLUGIN_MAIN} at --ref.

Modes (default: copy the package to trunk and tags/<version> in one commit):
  -b, --build-only  Build build/${PLUGIN_SLUG}/ and build/${PLUGIN_SLUG}.zip; no SVN.
      --tag-only    Create tags/<version> only; leave trunk (and the live Stable tag) alone.
      --promote     Replace trunk with an existing tags/<version> (no build).

Options:
  -n, --dry-run     Build and stage the SVN working copy, show svn status, do not commit.
  -y, --yes         Skip the confirmation prompt.
      --ref REF     Git ref to package (default: HEAD).
      --allow-dirty Allow uncommitted changes in the working tree (the package
                    is still built from --ref, never from the working tree).
      --skip-api    Do not query the WordPress.org plugin_information API afterwards.
      --strict-api  Exit non-zero if the API does not report <version> afterwards.
  -h, --help        Show this help.

Environment:
  WP_USERNAME / SVN_USERNAME  WordPress.org SVN username (prompted when interactive).
  WP_PASSWORD / SVN_PASSWORD  WordPress.org SVN password (prompted silently when interactive).
  WP_SVN_REPO                 SVN base URL (default: ${WP_SVN_REPO}).
EOF
}

die() {
	echo "Error: $*" >&2
	exit 1
}

require_cmd() {
	local cmd
	for cmd in "$@"; do
		command -v "${cmd}" >/dev/null 2>&1 || die "required command '${cmd}' was not found on PATH."
	done
}

extract_stable_tag() {
	tr -d '\r' | sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\)[[:space:]]*$/\1/p' | head -n 1
}

query_wp_api_version() {
	local payload
	payload="$(curl -fsSL --max-time 15 "${WP_API_URL}" 2>/dev/null)" || return 2
	printf '%s' "${payload}" | php -r '$d = json_decode( stream_get_contents( STDIN ), true ); echo isset( $d["version"] ) ? $d["version"] : "";'
}

confirm() {
	if [[ "${AUTO_YES}" -eq 1 ]]; then
		return 0
	fi
	[[ -t 0 ]] || die "confirmation required (pass --yes for non-interactive runs)."
	local answer
	read -r -p "$1 [y/N] " answer
	case "${answer}" in
		y | Y | yes | YES) return 0 ;;
		*)
			echo "Aborted without modifying SVN."
			exit 1
			;;
	esac
}

load_credentials() {
	WP_USERNAME="${WP_USERNAME:-${SVN_USERNAME:-}}"
	WP_PASSWORD="${WP_PASSWORD:-${SVN_PASSWORD:-}}"
	if [[ -z "${WP_USERNAME}" ]]; then
		[[ -t 0 ]] || die "WP_USERNAME (or SVN_USERNAME) must be set when stdin is not interactive."
		read -r -p "WordPress.org SVN username: " WP_USERNAME
	fi
	if [[ -z "${WP_PASSWORD}" ]]; then
		[[ -t 0 ]] || die "WP_PASSWORD (or SVN_PASSWORD) must be set when stdin is not interactive."
		read -r -s -p "WordPress.org SVN password: " WP_PASSWORD
		echo
	fi
	[[ -n "${WP_USERNAME}" && -n "${WP_PASSWORD}" ]] || die "SVN credentials cannot be empty."
}

svn_auth() {
	printf '%s\n' "${WP_PASSWORD}" | svn "$@" \
		--username "${WP_USERNAME}" \
		--password-from-stdin \
		--non-interactive \
		--no-auth-cache
}

check_api() {
	[[ "${CHECK_API}" -eq 1 ]] || return 0
	local api_version
	api_version="$(query_wp_api_version || true)"
	if [[ -z "${api_version}" ]]; then
		echo "Warning: could not read the WordPress.org plugin_information API." >&2
		if [[ "${STRICT_API}" -eq 1 ]]; then
			exit 1
		fi
		return 0
	fi
	echo "WordPress.org plugin_information API reports version: ${api_version}"
	if [[ "${api_version}" != "${VERSION}" ]]; then
		echo "Note: the WordPress.org API cache can take a few minutes to show ${VERSION}." >&2
		if [[ "${STRICT_API}" -eq 1 ]]; then
			exit 1
		fi
	fi
	return 0
}

# Development-only paths that must never appear in the package.
LEAK_PATTERN="^${PLUGIN_SLUG}/(\.git(/|$)|\.github/|\.gitattributes$|\.gitignore$|\.distignore$|\.editorconfig$|\.vscode/|\.cursor/|\.claude/|\.phpunit\.result\.cache$|bin/|build/|ci/|docs/|node_modules/|policies/|rules/|tests/|vendor/|composer\.(json|lock)$|conftest\.toml$|package(-lock)?\.json$|yarn(\.lock|-error\.log)$|phpcs\.xml(\.dist)?$|phpcompat\.xml\.dist$|phpunit\.xml(\.dist)?$|README\.md$|CHANGELOG\.md$|includes/config/\.gitignore$|.*\.zip$)"

# Runtime files the plugin cannot load without.
REQUIRED_FILES=(
	"${PLUGIN_MAIN}"
	readme.txt
	LICENSE.txt
	autoload.php
	index.php
	includes/class-central-connect.php
	includes/class-central-connect-version-check.php
	includes/config/config.plugin.php
	src/Rest/Server.php
	src/Authentication/Token.php
	assets/js/admin.js
	assets/style/admin.css
)

build_package() {
	require_cmd git php rsync zip unzip

	echo "== Building ${PLUGIN_SLUG} ${VERSION} from ${GIT_REF} ($(git -C "${PLUGIN_ROOT}" rev-parse --short "${GIT_REF}"))"
	rm -rf "${PACKAGE_DIR}" "${ZIP_FILE}" "${BUILD_ROOT}/export"
	mkdir -p "${BUILD_ROOT}/export" "${PACKAGE_DIR}"

	git -C "${PLUGIN_ROOT}" archive --format=tar "${GIT_REF}" | tar -x -C "${BUILD_ROOT}/export"
	rsync -a --delete --exclude-from="${PLUGIN_ROOT}/.distignore" "${BUILD_ROOT}/export/" "${PACKAGE_DIR}/"
	rm -rf "${BUILD_ROOT}/export"

	echo "== Checking release metadata"
	local meta
	meta="$(php "${PLUGIN_ROOT}/bin/plugin-meta.php" "${PACKAGE_DIR}")"
	php -r '
		$m = json_decode( $argv[1], true );
		$v = $argv[2];
		$errors = array();
		if ( $m["plugin"]["version"] !== $v ) { $errors[] = "plugin header Version is " . $m["plugin"]["version"]; }
		if ( $m["readme"]["stable_tag"] !== $v ) { $errors[] = "readme Stable tag is " . $m["readme"]["stable_tag"]; }
		if ( ( $m["readme"]["changelog"][0] ?? "" ) !== $v ) { $errors[] = "newest changelog entry is " . ( $m["readme"]["changelog"][0] ?? "missing" ); }
		if ( $m["plugin"]["requires_php"] !== $m["readme"]["requires_php"] ) { $errors[] = "Requires PHP differs between plugin header and readme"; }
		foreach ( $errors as $e ) { fwrite( STDERR, "Error: " . $e . ", expected " . $v . PHP_EOL ); }
		exit( $errors ? 1 : 0 );
	' "${meta}" "${VERSION}" || die "release metadata does not match ${VERSION}."

	echo "== PHP syntax lint"
	local file lint_failed=0
	while IFS= read -r -d '' file; do
		php -l "${file}" >/dev/null || lint_failed=1
	done < <(find "${PACKAGE_DIR}" -name '*.php' -print0)
	[[ "${lint_failed}" -eq 0 ]] || die "PHP lint failed in the package."

	echo "== Creating ${ZIP_FILE#"${PLUGIN_ROOT}/"}"
	(cd "${BUILD_ROOT}" && zip -rqX "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}")

	echo "== Verifying package contents"
	local entries leaked missing="" required
	entries="$(unzip -Z1 "${ZIP_FILE}")"
	leaked="$(grep -E "${LEAK_PATTERN}" <<<"${entries}" || true)"
	if [[ -n "${leaked}" ]]; then
		echo "${leaked}" >&2
		die "development-only paths are present in the package."
	fi
	for required in "${REQUIRED_FILES[@]}"; do
		grep -qxF "${PLUGIN_SLUG}/${required}" <<<"${entries}" || missing="${missing} ${required}"
	done
	[[ -z "${missing}" ]] || die "package is missing:${missing}"

	echo "Package OK: $(grep -c -v '/$' <<<"${entries}") files."
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		-b | --build-only) MODE="build" ;;
		--tag-only) MODE="tag" ;;
		--promote) MODE="promote" ;;
		-n | --dry-run) DRY_RUN=1 ;;
		-y | --yes) AUTO_YES=1 ;;
		--allow-dirty) ALLOW_DIRTY=1 ;;
		--skip-api) CHECK_API=0 ;;
		--strict-api)
			CHECK_API=1
			STRICT_API=1
			;;
		--ref)
			[[ $# -ge 2 ]] || die "--ref requires a value."
			GIT_REF="$2"
			shift
			;;
		--ref=*) GIT_REF="${1#--ref=}" ;;
		-h | --help)
			usage
			exit 0
			;;
		-*)
			echo "Error: unknown option: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			[[ -z "${VERSION}" ]] || die "unexpected extra argument: $1"
			VERSION="$1"
			;;
	esac
	shift
done

cd "${PLUGIN_ROOT}"
require_cmd git

git rev-parse --verify --quiet "${GIT_REF}^{commit}" >/dev/null || die "git ref '${GIT_REF}' does not exist."

if [[ -z "${VERSION}" ]]; then
	VERSION="$(git show "${GIT_REF}:${PLUGIN_MAIN}" | sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\)[[:space:]]*$/\1/p' | head -n 1)"
fi
[[ "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "invalid or missing version '${VERSION}'. Expected X.Y.Z."

if [[ "${MODE}" != "promote" && "${ALLOW_DIRTY}" -ne 1 && -n "$(git status --porcelain --untracked-files=no)" ]]; then
	die "the working tree has uncommitted changes; commit them or pass --allow-dirty (the package is built from ${GIT_REF})."
fi

echo "Plugin:   ${PLUGIN_SLUG}"
echo "Version:  ${VERSION}"
echo "Mode:     ${MODE}$([[ "${DRY_RUN}" -eq 1 ]] && echo ' (dry run)')"
[[ "${MODE}" == "build" ]] || echo "SVN repo: ${WP_SVN_REPO}"

TAG_URL="${WP_SVN_REPO}/tags/${VERSION}"
TRUNK_URL="${WP_SVN_REPO}/trunk"

if [[ "${MODE}" == "promote" ]]; then
	require_cmd svn svnmucc curl php
	svn info "${TAG_URL}" >/dev/null 2>&1 || die "tags/${VERSION} was not found at ${WP_SVN_REPO}."
	TAG_STABLE="$(svn cat "${TAG_URL}/readme.txt" | extract_stable_tag)"
	[[ "${TAG_STABLE}" == "${VERSION}" ]] || die "tags/${VERSION}/readme.txt Stable tag is '${TAG_STABLE}', expected '${VERSION}'."
	echo "Verified tags/${VERSION}/readme.txt Stable tag."
	if [[ "${DRY_RUN}" -eq 1 ]]; then
		echo "[dry-run] Would run: svnmucc rm ${TRUNK_URL} cp HEAD ${TAG_URL} ${TRUNK_URL}"
		exit 0
	fi
	load_credentials
	confirm "Replace ${TRUNK_URL} with ${TAG_URL}?"
	printf '%s\n' "${WP_PASSWORD}" | svnmucc \
		-m "Releasing ${VERSION} to trunk" \
		--username "${WP_USERNAME}" \
		--password-from-stdin \
		--non-interactive \
		--no-auth-cache \
		rm "${TRUNK_URL}" \
		cp HEAD "${TAG_URL}" "${TRUNK_URL}"
	TRUNK_STABLE="$(svn cat "${TRUNK_URL}/readme.txt" | extract_stable_tag)"
	[[ "${TRUNK_STABLE}" == "${VERSION}" ]] || die "trunk/readme.txt Stable tag is '${TRUNK_STABLE}', expected '${VERSION}'."
	echo "Trunk now matches tags/${VERSION}."
	check_api
	exit 0
fi

build_package

if [[ "${MODE}" == "build" ]]; then
	echo "Build only: ${PACKAGE_DIR#"${PLUGIN_ROOT}/"} and ${ZIP_FILE#"${PLUGIN_ROOT}/"} are ready."
	exit 0
fi

require_cmd svn curl
if svn info "${TAG_URL}" >/dev/null 2>&1; then
	die "tags/${VERSION} already exists on WordPress.org; bump the version instead of overwriting a release."
fi

echo "== Preparing SVN working copy in ${SVN_DIR#"${PLUGIN_ROOT}/"}"
rm -rf "${SVN_DIR}"
svn checkout --quiet --depth=immediates "${WP_SVN_REPO}" "${SVN_DIR}"
svn update --quiet --set-depth=infinity "${SVN_DIR}/trunk"

if [[ "${MODE}" == "release" ]]; then
	rsync -a --delete --exclude=.svn "${PACKAGE_DIR}/" "${SVN_DIR}/trunk/"
	(
		cd "${SVN_DIR}"
		svn status trunk | sed -n 's/^?[[:space:]]*//p' | while IFS= read -r path; do svn add --quiet --parents "${path}"; done
		svn status trunk | sed -n 's/^![[:space:]]*//p' | while IFS= read -r path; do svn rm --quiet "${path}"; done
		svn copy --quiet trunk "tags/${VERSION}"
	)
else
	mkdir -p "${SVN_DIR}/tags"
	rsync -a --exclude=.svn "${PACKAGE_DIR}/" "${SVN_DIR}/tags/${VERSION}/"
	svn add --quiet --parents "${SVN_DIR}/tags/${VERSION}"
fi

echo "== Pending SVN changes"
(cd "${SVN_DIR}" && svn status | sed -n '1,200p')

if [[ "${DRY_RUN}" -eq 1 ]]; then
	echo "[dry-run] No changes were committed to SVN. Inspect ${SVN_DIR#"${PLUGIN_ROOT}/"} if needed."
	exit 0
fi

load_credentials
if [[ "${MODE}" == "release" ]]; then
	confirm "Commit ${PLUGIN_SLUG} ${VERSION} to trunk and tags/${VERSION}?"
	COMMIT_MESSAGE="Release ${VERSION}"
else
	confirm "Commit tags/${VERSION} (trunk unchanged)?"
	COMMIT_MESSAGE="Tag ${VERSION}"
fi

(cd "${SVN_DIR}" && svn_auth commit --message "${COMMIT_MESSAGE}")

TAG_STABLE="$(svn cat "${TAG_URL}/readme.txt" | extract_stable_tag)"
[[ "${TAG_STABLE}" == "${VERSION}" ]] || die "tags/${VERSION}/readme.txt Stable tag is '${TAG_STABLE}', expected '${VERSION}'."
echo "Confirmed tags/${VERSION}/readme.txt Stable tag."

if [[ "${MODE}" == "release" ]]; then
	TRUNK_STABLE="$(svn cat "${TRUNK_URL}/readme.txt" | extract_stable_tag)"
	[[ "${TRUNK_STABLE}" == "${VERSION}" ]] || die "trunk/readme.txt Stable tag is '${TRUNK_STABLE}', expected '${VERSION}'."
	echo "Confirmed trunk/readme.txt Stable tag."
	check_api
else
	echo "Trunk was not changed. Promote later with: $0 --promote ${VERSION}"
fi
