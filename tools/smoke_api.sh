#!/bin/bash
# Prueba de humo de la API REST desplegada (Hito 2).
# Uso:  DEMO_PASSWORD=... bash tools/smoke_api.sh [https://trivoxa.cl/TriTransportes/api/v1]
# En el servidor:  set -a; . ./.env; set +a; bash tools/smoke_api.sh
# No imprime la contraseña ni el token completo.
set -u
B="${1:-https://trivoxa.cl/TriTransportes/api/v1}"
: "${DEMO_PASSWORD:?Defina DEMO_PASSWORD}"
j() { php -r '$d=json_decode(stream_get_contents(STDIN),true); echo json_encode(eval("return $argv[1];"), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;' "$1"; }
paso() { printf '\n### %s\n' "$1"; }

echo "Prueba de humo de $B — $(date '+%Y-%m-%d %H:%M %Z')"

paso "POST /auth/login (Supervisor) → 200"
R=$(curl -s -X POST "$B/auth/login" -H 'Content-Type: application/json' \
    -d "{\"email\":\"supervisor@tritransportes.cl\",\"password\":\"$DEMO_PASSWORD\"}")
echo "$R" | j '["token" => substr($d["token"] ?? "", 0, 16) . "…", "expires_in" => $d["expires_in"] ?? null, "usuario" => $d["usuario"] ?? $d]'
T=$(echo "$R" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"] ?? "";')
TC=$(curl -s -X POST "$B/auth/login" -H 'Content-Type: application/json' \
    -d "{\"email\":\"conductor@tritransportes.cl\",\"password\":\"$DEMO_PASSWORD\"}" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"] ?? "";')

paso "GET /viajes sin token → 401"
curl -s -w ' [HTTP %{http_code}]\n' "$B/viajes"

paso "GET /viajes?estado=completado&per_page=2&sort=-fecha (Supervisor) → 200 paginado"
curl -s "$B/viajes?estado=completado&per_page=2&sort=-fecha" -H "Authorization: Bearer $T" \
    | j '["meta" => $d["meta"], "primero" => array_intersect_key($d["data"][0], array_flip(["id","estado","fecha","vehiculo","conductor","transiciones_permitidas"]))]'

paso "GET /viajes (Conductor) → solo sus viajes"
curl -s "$B/viajes?per_page=1" -H "Authorization: Bearer $TC" | j '["total" => $d["meta"]["total"], "conductor" => $d["data"][0]["conductor"] ?? null]'

paso "POST /viajes (Conductor) → 403"
curl -s -w ' [HTTP %{http_code}]\n' -X POST "$B/viajes" -H "Authorization: Bearer $TC" -H 'Content-Type: application/json' -d '{}'

paso "POST /viajes con datos inválidos (Supervisor) → 422"
curl -s -w ' [HTTP %{http_code}]\n' -X POST "$B/viajes" -H "Authorization: Bearer $T" -H 'Content-Type: application/json' -d '{"fecha":"2026-02-30","unidad":"kg"}'

ID=$(curl -s "$B/viajes?estado=completado&per_page=1" -H "Authorization: Bearer $T" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"][0]["id"];')
paso "PATCH /viajes/$ID/estado completado → en_ruta → 409"
curl -s -w ' [HTTP %{http_code}]\n' -X PATCH "$B/viajes/$ID/estado" -H "Authorization: Bearer $T" -H 'Content-Type: application/json' -d '{"estado":"en_ruta"}'

paso "GET /vehiculos/1/rendimiento?desde=2026-08-01 (Supervisor) → 200"
curl -s "$B/vehiculos/1/rendimiento?desde=2026-08-01" -H "Authorization: Bearer $T" | j 'array_slice($d, 0, 3)'

paso "GET /mantenciones?estado=pendiente&per_page=1 (Conductor) → 403"
curl -s -w ' [HTTP %{http_code}]\n' "$B/mantenciones?estado=pendiente&per_page=1" -H "Authorization: Bearer $TC"
