#!/usr/bin/env bash
# Crea un ZIP instalable en WordPress: la raíz del archivo debe ser la carpeta del plugin.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
mkdir -p "${DIST}"
ZIP="${DIST}/pd-imaging-orden-digital.zip"
rm -f "${ZIP}"
(
  cd "${ROOT}"
  zip -r "${ZIP}" pd-imaging-orden-digital/ \
    -x "*.git*" \
    -x "*__MACOSX*" \
    -x "*.DS_Store"
)
echo "ZIP listo: ${ZIP}"
