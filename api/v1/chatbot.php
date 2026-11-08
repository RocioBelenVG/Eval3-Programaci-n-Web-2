<?php
/**
 * API v1 — Chatbot IA (Groq — Llama 3.3 70B — GRATUITO)
 * POST /api/v1/chatbot.php
 * Headers: X-CSRF-Token: {token}
 * Body JSON: {"message": "...", "history": [{"role":"user","content":"..."},{"role":"assistant","content":"..."}]}
 *
 * Proveedor: Groq (https://console.groq.com) — Cuenta gratuita, sin tarjeta de crédito
 * Modelo: llama-3.3-70b-versatile — Llama 3.3 de Meta, 70B parámetros
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar CSRF en header AJAX (lectura robusta, insensible a mayúsculas/minúsculas;
// HTTP/2 entrega los nombres de cabecera en minúsculas).
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($csrfToken === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $hk => $hv) {
        if (strcasecmp($hk, 'X-CSRF-Token') === 0) { $csrfToken = $hv; break; }
    }
}
$csrfToken = trim((string)$csrfToken);
if (!$csrfToken || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

// Rate limiting: 60 mensajes por sesión
$_SESSION['chatbot_count'] = ($_SESSION['chatbot_count'] ?? 0);
if ($_SESSION['chatbot_count'] >= 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Límite de mensajes alcanzado. Cierre y reabra sesión para continuar.']);
    exit;
}

// Leer y validar entrada
$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$userMessage = trim((string)($body['message'] ?? ''));
$historyRaw  = is_array($body['history'] ?? null) ? $body['history'] : [];

if ($userMessage === '' || strlen($userMessage) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensaje inválido']);
    exit;
}

// Verificar API Key
if (!GROQ_API_KEY || str_contains(GROQ_API_KEY, 'inserte_su_clave')) {
    http_response_code(503);
    echo json_encode([
        'error' => 'Chatbot no configurado. Obtenga su API Key GRATUITA en console.groq.com (sin tarjeta de crédito) e ingrésela en config.php → constante GROQ_API_KEY.'
    ]);
    exit;
}

// System prompt contextual de TriTransportes
$empresaNombre = htmlspecialchars_decode(Auth::user()['empresa_nombre'] ?? 'N/A', ENT_QUOTES);
$rolNombre     = htmlspecialchars_decode(Auth::rolNombre(), ENT_QUOTES);

$systemPrompt = "Eres el asistente virtual de TriTransportes, una plataforma de gestión operacional para empresas chilenas de áridos, transporte, construcción vial y maquinaria pesada.

MÓDULOS DEL SISTEMA:
- Dashboard: KPIs en tiempo real y resumen operacional
- Partes de Faena: Registro diario de actividades en terreno
- Combustible: Control de cargas, litros y costos por vehículo/equipo
- Maquinaria: Flota de equipos, mantenciones preventivas/correctivas, horómetros
- Transporte: Registro de viajes y guías de despacho
- Áridos: Producción, stock y despachos de material
- Contratos: Avance físico y financiero de proyectos
- Documentos: Control documental con alertas de vencimiento
- Seguridad: Checklists de inspección e incidentes
- Personal: Gestión de Trabajadores, Conductores y Operadores (sección PERSONAL en menú)
- Flota: Gestión de Vehículos y Equipos (sección FLOTA en menú)
- Administración: Usuarios, roles, permisos y tablas maestras
- Ayuda/Manual: Manual de usuario completo disponible en el menú lateral

CONTEXTO DEL USUARIO ACTUAL:
- Empresa: {$empresaNombre}
- Rol: {$rolNombre}

INSTRUCCIONES:
- Responde SIEMPRE en español neutro chileno
- Sé conciso, claro y práctico
- Si el usuario pregunta cómo hacer algo, explica los pasos exactos en el sistema
- Cuando corresponda, indica el menú o sección donde encontrar la funcionalidad
- No inventes funcionalidades inexistentes
- Si no sabes algo específico, indícalo y sugiere contactar al administrador
- Puedes ayudar con preguntas operacionales del rubro áridos/transporte/construcción";

// Construir historial sanitizado (últimas 10 interacciones)
$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach (array_slice($historyRaw, -10) as $h) {
    if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && !empty($h['content'])) {
        $messages[] = [
            'role'    => $h['role'],
            'content' => mb_substr((string)$h['content'], 0, 800),
        ];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

// Llamada a la API de Groq (compatible con formato OpenAI)
$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $messages,
    'max_tokens'  => 1024,
    'temperature' => 0.7,
    'stream'      => false,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Error de conexión con el servicio de IA. Verifique su conexión a internet.']);
    exit;
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['error']['message'] ?? 'Error del servicio de IA (código ' . $httpCode . ').';
    http_response_code(502);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? 'Sin respuesta del asistente.';

$_SESSION['chatbot_count']++;

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
