<?php
/**
 * Recebe os formulários do site (página de contato, modal "Experimente Agora!" e
 * calculadoras) e envia tudo por e-mail para a caixa de contato.
 *
 * Sem dependências nem banco de dados: só mail() e a biblioteca padrão do PHP.
 * Nenhum dado pessoal é gravado em disco (o único arquivo criado é um contador
 * temporário de limite de envios, com hash do IP e horários).
 */
declare(strict_types=1);

const DESTINO = 'contato@vorteilclub.com.br';

// Remetente do e-mail. Deixar null usa o remetente padrão da hospedagem, que já é
// autenticado (SPF) pela Hostinger. O e-mail do domínio fica no Google Workspace e o
// SPF do domínio só autoriza o Google, então um remetente @vorteilclub.com.br enviado
// daqui seria tratado como falsificação. O visitante vai em Reply-To, então "Responder"
// já escreve direto para ele.
const REMETENTE = null;

const ORIGENS_PERMITIDAS = ['https://vorteilclub.com.br', 'https://www.vorteilclub.com.br'];
const FAIXAS_FUNCIONARIOS = ['1-10', '11-50', '51-200', '201-500', '500+'];
const MAX_CORPO_BYTES = 20000;
const MIN_MS_NA_PAGINA = 1500;       // formulário enviado antes disso é robô
const LIMITE_POR_IP_NA_HORA = 8;
const LIMITE_GERAL_NA_HORA = 120;

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $status, bool $ok, string $erro = ''): never
{
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $erro]);
    exit;
}

function cortar(string $s, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

/** Texto livre: remove caracteres de controle (menos quebra de linha) e limita o tamanho. */
function texto(mixed $v, int $max): string
{
    if (is_bool($v)) {
        return $v ? 'sim' : 'não';
    }
    if (!is_scalar($v)) {
        return '';
    }
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v) ?? '';
    return cortar(trim($s), $max);
}

/** Uma linha só: nada de quebra de linha, para nunca virar cabeçalho de e-mail. */
function linha(mixed $v, int $max): string
{
    return trim(preg_replace('/\s+/u', ' ', texto($v, $max)) ?? '');
}

/**
 * Assunto em UTF-8 sem quebra de linha (o mail() não aceita): cada grupo de 12
 * caracteres vira uma "encoded-word" curta, separadas por espaço.
 */
function assuntoMime(string $s): string
{
    $caracteres = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $palavras = [];
    foreach (array_chunk($caracteres, 12) as $grupo) {
        $palavras[] = '=?UTF-8?B?' . base64_encode(implode('', $grupo)) . '?=';
    }
    return implode(' ', $palavras);
}

function emailValido(string $e): bool
{
    return $e !== '' && strlen($e) <= 254 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function qtdDigitos(string $s): int
{
    return preg_match_all('/\d/', $s);
}

/** Transforma arrays aninhados em linhas "chave: valor". */
function achatar(mixed $v, string $prefixo = '', int $nivel = 0): array
{
    $linhas = [];
    if (is_array($v)) {
        if ($nivel >= 3) {
            return $linhas;
        }
        foreach (array_slice($v, 0, 40, true) as $k => $val) {
            $chave = $prefixo === '' ? (string) $k : $prefixo . '.' . $k;
            $linhas = array_merge($linhas, achatar($val, $chave, $nivel + 1));
        }
    } elseif ($v !== null && $v !== '') {
        $linhas[] = linha($prefixo, 60) . ': ' . linha($v, 300);
    }
    return $linhas;
}

/** Limite de envios por janela de 1h. Se não der para controlar, não bloqueia lead legítimo. */
function dentroDoLimite(string $chave, int $max): bool
{
    $arq = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vc_rl_' . hash('sha256', $chave);
    $h = @fopen($arq, 'c+');
    if (!$h) {
        return true;
    }
    flock($h, LOCK_EX);
    $agora = time();
    $marcas = array_values(array_filter(
        array_map('intval', explode(',', (string) stream_get_contents($h))),
        static fn(int $t): bool => $t > $agora - 3600
    ));
    $permitido = count($marcas) < $max;
    if ($permitido) {
        $marcas[] = $agora;
    }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, implode(',', $marcas));
    flock($h, LOCK_UN);
    fclose($h);
    return $permitido;
}

function ipDoVisitante(): string
{
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $primeiro = trim(explode(',', $xff)[0]);
        if (filter_var($primeiro, FILTER_VALIDATE_IP) !== false) {
            return $primeiro;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
}

// ---------- Requisição ----------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, false, 'metodo');
}

$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origem !== '' && !in_array($origem, ORIGENS_PERMITIDAS, true)) {
    responder(403, false, 'origem');
}

$bruto = file_get_contents('php://input', false, null, 0, MAX_CORPO_BYTES + 1);
if ($bruto === false || $bruto === '' || strlen($bruto) > MAX_CORPO_BYTES) {
    responder(413, false, 'tamanho');
}
$dados = json_decode($bruto, true);
if (!is_array($dados)) {
    responder(400, false, 'json');
}

// Isca para robôs: o site nunca preenche este campo. Finge sucesso e não envia nada.
if (texto($dados['website'] ?? '', 10) !== '') {
    responder(200, true);
}
// Robôs que postam direto no endpoint não esperam o tempo de um humano na página.
if (!isset($dados['elapsed']) || !is_numeric($dados['elapsed']) || (float) $dados['elapsed'] < MIN_MS_NA_PAGINA) {
    responder(422, false, 'rapido');
}

$nome = linha($dados['name'] ?? '', 100);
$pagina = linha($dados['page_url'] ?? '', 200);
$ehCalculadora = isset($dados['calculator']);
$respostaA = null;   // e-mail do visitante, se houver (vira Reply-To)
$campos = [];        // pares [rótulo, valor] do corpo

if ($ehCalculadora) {
    $contato = linha($dados['contact'] ?? '', 150);
    if ($nome === '' || $contato === '') {
        responder(422, false, 'campos');
    }
    if (strpos($contato, '@') !== false) {
        if (!emailValido($contato)) {
            responder(422, false, 'email');
        }
        $respostaA = $contato;
    } elseif (qtdDigitos($contato) < 8) {
        responder(422, false, 'whatsapp');
    }
    $nomesCalc = ['absenteismo' => 'absenteísmo', 'sinistralidade' => 'sinistralidade'];
    $qual = linha($dados['calculator'], 40);
    $titulo = 'Lead da calculadora de ' . ($nomesCalc[$qual] ?? $qual);
    $campos[] = ['Nome', $nome];
    $campos[] = ['Contato (WhatsApp ou e-mail)', $contato];
    if (!empty($dados['mode'])) {
        $campos[] = ['Modalidade do plano', linha($dados['mode'], 20)];
    }
} else {
    $empresa = linha($dados['company'] ?? '', 120);
    $email = linha($dados['work_email'] ?? '', 254);
    $faixa = linha($dados['employee_count'] ?? '', 20);
    $whats = linha($dados['whatsapp'] ?? '', 40);
    if ($nome === '' || $empresa === '' || $email === '' || $faixa === '' || $whats === '') {
        responder(422, false, 'campos');
    }
    if (!emailValido($email)) {
        responder(422, false, 'email');
    }
    if (!in_array($faixa, FAIXAS_FUNCIONARIOS, true)) {
        responder(422, false, 'funcionarios');
    }
    if (qtdDigitos($whats) < 8) {
        responder(422, false, 'whatsapp');
    }
    $respostaA = $email;
    $titulo = linha($dados['source'] ?? '', 40) === 'site_cta' ? 'Lead pelo botão do site' : 'Contato pelo formulário';
    $campos[] = ['Nome', $nome];
    $campos[] = ['Empresa', $empresa];
    $campos[] = ['E-mail', $email];
    $campos[] = ['WhatsApp', $whats];
    $campos[] = ['Número de funcionários', $faixa];
}

if (!dentroDoLimite('ip:' . ipDoVisitante(), LIMITE_POR_IP_NA_HORA) || !dentroDoLimite('geral', LIMITE_GERAL_NA_HORA)) {
    responder(429, false, 'limite');
}

// ---------- E-mail ----------

$corpo = "{$titulo}\n\n";
foreach ($campos as [$rotulo, $valor]) {
    $corpo .= "{$rotulo}: {$valor}\n";
}
if ($pagina !== '') {
    $corpo .= "Página: {$pagina}\n";
}
if ($ehCalculadora) {
    $inputs = is_array($dados['inputs'] ?? null) ? $dados['inputs'] : [];
    unset($inputs['result_summary']);
    $resumo = achatar($dados['result_summary'] ?? null, 'Resumo');
    if ($resumo) {
        $corpo .= "\n" . implode("\n", $resumo) . "\n";
    }
    $valores = achatar($inputs);
    if ($valores) {
        $corpo .= "\nDados informados na calculadora:\n" . implode("\n", $valores) . "\n";
    }
}
date_default_timezone_set('America/Sao_Paulo');
$corpo .= "\nEnviado pelo site em " . date('d/m/Y H:i') . " (horário de Brasília).\n";

$assunto = '[Site] ' . $titulo . ': ' . cortar($nome, 60) . (isset($empresa) && $empresa !== '' ? ' (' . cortar($empresa, 60) . ')' : '');
$assuntoCodificado = assuntoMime($assunto);

$cabecalhos = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: vorteilclub-site',
];
if (REMETENTE !== null) {
    array_unshift($cabecalhos, 'From: ' . REMETENTE);
}
if ($respostaA !== null) {
    $cabecalhos[] = 'Reply-To: ' . $respostaA;
}

if (!mail(DESTINO, $assuntoCodificado, $corpo, implode("\n", $cabecalhos))) {
    error_log('contato.php: mail() recusou o envio');
    responder(502, false, 'envio');
}

responder(200, true);
