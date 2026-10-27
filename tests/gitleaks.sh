#!/usr/bin/env bash
#
# Download a SHA-pinned gitleaks binary and scan git history, the working
# tree, or a directory (release zip unpack).
#
# Usage:
#   bash tests/gitleaks.sh --history
#   bash tests/gitleaks.sh --no-git
#   bash tests/gitleaks.sh --no-git --source dist
#
set -euo pipefail

GITLEAKS_VERSION='8.30.1'
GITLEAKS_LINUX_X64_SHA256='551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb'
GITLEAKS_DARWIN_ARM64_SHA256='b40ab0ae55c505963e365f271a8d3846efbc170aa17f2607f13df610a9aeb6a5'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SOURCE="$ROOT"
MODE=''

while [ $# -gt 0 ]; do
	case "$1" in
		--history)
			MODE='history'
			shift
			;;
		--no-git)
			MODE='no-git'
			shift
			;;
		--source)
			SOURCE="$2"
			shift 2
			;;
		*)
			echo "Usage: $0 --history|--no-git [--source DIR]" >&2
			exit 2
			;;
	esac
done

if [ -z "$MODE" ]; then
	echo "Usage: $0 --history|--no-git [--source DIR]" >&2
	exit 2
fi

if [ ! -d "$SOURCE" ]; then
	echo "FAIL: source is not a directory: $SOURCE" >&2
	exit 1
fi

OS="$(uname -s)"
ARCH="$(uname -m)"
ASSET=''
EXPECT_SHA=''
case "${OS}:${ARCH}" in
	Linux:x86_64|Linux:amd64)
		ASSET="gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz"
		EXPECT_SHA="$GITLEAKS_LINUX_X64_SHA256"
		;;
	Darwin:arm64)
		ASSET="gitleaks_${GITLEAKS_VERSION}_darwin_arm64.tar.gz"
		EXPECT_SHA="$GITLEAKS_DARWIN_ARM64_SHA256"
		;;
	*)
		echo "FAIL: no pinned gitleaks archive for ${OS}/${ARCH}" >&2
		exit 1
		;;
esac

WORKDIR="${RUNNER_TEMP:-/tmp}/struo-gitleaks-${GITLEAKS_VERSION}"
mkdir -p "$WORKDIR"
ARCHIVE="$WORKDIR/$ASSET"
BIN="$WORKDIR/gitleaks"
URL="https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/${ASSET}"

if [ ! -x "$BIN" ]; then
	curl -fsSL "$URL" -o "$ARCHIVE"
	if command -v sha256sum >/dev/null 2>&1; then
		echo "${EXPECT_SHA}  ${ARCHIVE}" | sha256sum -c -
	else
		echo "${EXPECT_SHA}  ${ARCHIVE}" | shasum -a 256 -c -
	fi
	tar -xzf "$ARCHIVE" -C "$WORKDIR" gitleaks
	chmod +x "$BIN"
fi

CONFIG=()
if [ -f "$ROOT/.gitleaks.toml" ]; then
	CONFIG=( --config "$ROOT/.gitleaks.toml" )
fi

if [ "$MODE" = 'history' ]; then
	"$BIN" detect --source "$SOURCE" --redact --verbose --exit-code 1 "${CONFIG[@]}"
else
	"$BIN" detect --source "$SOURCE" --no-git --redact --verbose --exit-code 1 "${CONFIG[@]}"
fi
