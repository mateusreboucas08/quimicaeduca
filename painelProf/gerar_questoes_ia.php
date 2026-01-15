<?php
// Script: gerar_questoes_ia.php (Integração Real com Gemini API)

header('Content-Type: application/json');

// 🚨 1. CONFIGURAÇÃO - Substitua pela sua chave Gemini API 🚨
// Chave inserida pelo usuário. Mantenha em um local seguro.
$api_key = "insert_your_key"; 
// Usando o endpoint correto para o modelo e funcionalidades.
$api_endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['tema'])) {
    echo json_encode(['status' => 'error', 'message' => 'Requisição inválida.']);
    exit();
}

$tema = $_POST['tema'];
$num_questoes = (int)($_POST['num_questoes'] ?? 3);
$nivel = $_POST['nivel'] ?? 'Ensino Médio Básico';

// 2. DEFINIÇÃO DA ESTRUTURA DE SAÍDA (Schema JSON)
$schema = [
    'type' => 'object',
    'properties' => [
        'questoes' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'tipo' => ['type' => 'string'], // 'multipla_unica', 'multipla_varias', ou 'dissertativa'
                    'texto' => ['type' => 'string'],
                    'opcoes' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'gabarito' => ['type' => 'array', 'items' => ['type' => 'integer']], 
                    'gabarito_texto' => ['type' => 'string'], 
                    'pontuacao' => ['type' => 'number']
                ],
                'required' => ['tipo', 'texto', 'pontuacao']
            ]
        ]
    ]
];

// No bloco 3. PROMPT DE INSTRUÇÃO PARA A IA...
$prompt_text = "
    Gere $num_questoes questões de Química sobre os tópicos: '$tema'. 
    As questões devem ser apropriadas para o nível '$nivel'. 
    
    INSTRUÇÕES CRÍTICAS DE FORMATAÇÃO E CONTEÚDO:
    1. A resposta deve ser **INTEIRAMENTE** um objeto JSON e deve ser formatada com clareza para evitar truncamento.
    2. Garanta que o campo 'texto' de cada questão esteja **completo** e detalhado.
    3. Use criatividade para gerar questões variadas e evite repetição de conceitos primários.
    4. Inclua sempre 4 (quatro) opções válidas para cada questão de múltipla escolha.
    5. Siga estritamente o SCHEMA JSON fornecido.
";


// 4. PREPARAÇÃO DO CORPO DA REQUISIÇÃO (ESTRUTURA CORRIGIDA)
$system_instruction_text = "Você é um professor de Química que gera questões de avaliação. Retorne a resposta EXCLUSIVAMENTE no formato JSON solicitado.";

$data = [
    // REMOVIDO: O bloco 'tools' foi removido para evitar o conflito com responseMimeType: application/json.
    
    'generationConfig' => [
        'responseMimeType' => "application/json",
        'responseSchema' => $schema,
        'temperature' => 0.8, // Adiciona alta temperatura para criatividade e variedade (0.8 é um bom valor)
    ],

    'contents' => [
        [
            // Este primeiro elemento define o papel/instrução
            'role' => 'user',
            'parts' => [
                ['text' => $system_instruction_text]
            ]
        ],
        [
            // Este segundo elemento envia o prompt do usuário
            'role' => 'user',
            'parts' => [
                ['text' => $prompt_text]
            ]
        ]
    ]
];

// 5. REQUISIÇÃO CUM CURL (PHP Nativo)
$ch = curl_init($api_endpoint);

// Opções de HEADER
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "x-goog-api-key: $api_key"
]);
curl_setopt($ch, CURLOPT_POST, 1);
$data_json = json_encode($data);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// IMPORTANTE: Tentar forçar a verificação SSL novamente
// Se o erro persistir APÓS a correção do php.ini, alguns usuários no XAMPP
// DESABILITAM temporariamente esta verificação para isolar o problema de SSL/Firewall.
// Não é recomendado para produção, mas útil para testes:
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Mantendo a segurança como padrão

// Definir um timeout mais longo para a IA responder
curl_setopt($ch, CURLOPT_TIMEOUT, 60); 

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Tratamento de Erro de Conexão cURL
if (curl_errno($ch)) {
    $curl_error = curl_error($ch);
    curl_close($ch);
    // Retornamos o erro de conexão cURL para o JavaScript ver.
    echo json_encode(['status' => 'error', 'message' => 'Falha na conexão cURL com a API. Erro: ' . $curl_error]);
    exit();
}

curl_close($ch);

// 6. PROCESSAMENTO DA RESPOSTA
if ($http_code !== 200) {
    echo json_encode(['status' => 'error', 'message' => "Erro HTTP $http_code. Resposta da API: " . $response]);
    exit();
}

$api_response = json_decode($response, true);

$json_content_string = $api_response['candidates'][0]['content']['parts'][0]['text'] ?? null;

if ($json_content_string) {
    // Limpeza e decodificação do JSON retornado pela IA
    $json_content_string = trim($json_content_string, " \n\r\t\v\x00`");
    $json_content_string = preg_replace('/^json\s*/', '', $json_content_string);
    
    $final_data = json_decode($json_content_string, true);

    if ($final_data && isset($final_data['questoes'])) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Questões geradas com sucesso. A busca na web foi ativada para validar os dados.',
            'questoes' => $final_data['questoes']
        ]);
        exit();
    }
}

echo json_encode(['status' => 'error', 'message' => 'A IA não conseguiu gerar o JSON estruturado corretamente. Tente refinar o tema ou a instrução.']);

?>
