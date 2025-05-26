<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $conn = new mysqli('localhost', 'root', '', 'bushido_academy');

    if ($conn->connect_error) {
        throw new Exception('Erro de conexão com o banco de dados: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8');

    $endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'OPTIONS') {
        header('HTTP/1.1 200 OK');
        exit();
    }

    $data = [];
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Dados JSON inválidos: ' . json_last_error_msg());
            }
        }
    }

    switch ($endpoint) {
        case 'login':
            if ($method === 'POST') {
                if (!isset($data['email']) || !isset($data['password']) || !isset($data['tipo'])) {
                    throw new Exception('Dados de login incompletos');
                }

                $email = $data['email'];
                $password = $data['password'];
                $tipo = $data['tipo'];

                try {
                    if ($tipo === 'admin') {
                        // Verificação hardcoded para administrador
                        if ($email === 'admin123@gmail.com' && $password === 'admin') {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Login realizado com sucesso!',
                                'user' => [
                                    'id' => 1,
                                    'nome' => 'Administrador',
                                    'email' => $email
                                ],
                                'tipo' => 'admin'
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Email ou senha incorretos.'
                            ]);
                        }
                    } else if ($tipo === 'professor') {
                        // Código existente para professores
                        $stmt = $conn->prepare("SELECT * FROM professores WHERE email = ? AND senha = ?");
                        $stmt->bind_param("ss", $email, $password);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();

                        if ($user) {
                            if ($user['status'] !== 'Ativo') {
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'Professor inativo. Entre em contato com a administração.'
                                ]);
                                break;
                            }

                            // Atualizar último acesso
                            $checkColumn = $conn->query("SHOW COLUMNS FROM professores LIKE 'ultimo_acesso'");
                            if ($checkColumn->num_rows > 0) {
                                $conn->query("UPDATE professores SET ultimo_acesso = NOW() WHERE id = " . $user['id']);
                            }

                            echo json_encode([
                                'success' => true,
                                'message' => 'Login realizado com sucesso!',
                                'user' => $user,
                                'tipo' => 'professor'
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Email ou senha incorretos.'
                            ]);
                        }
                    } else {
                        // Código existente para alunos
                        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND senha = ?");
                        $stmt->bind_param("ss", $email, $password);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();

                        if ($user) {
                            if ($user['status'] !== 'Ativo') {
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'Usuário inativo. Entre em contato com a administração.'
                                ]);
                                break;
                            }

                            // Verificar assinatura para alunos
                            $sql = "SELECT a.*, p.nome as plano_nome, p.valor, p.duracao_meses 
                                   FROM assinaturas a 
                                   JOIN planos p ON a.plano_id = p.id 
                                   WHERE a.usuario_id = ? 
                                   AND a.status = 'Ativa' 
                                   AND a.data_fim >= NOW() 
                                   ORDER BY a.data_fim DESC 
                                   LIMIT 1";

                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user['id']);
                            $stmt->execute();
                            $assinatura = $stmt->get_result()->fetch_assoc();

                            if (!$assinatura) {
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Sua assinatura está inativa. Por favor, escolha um plano.',
                                    'user' => $user,
                                    'tipo' => 'aluno',
                                    'assinatura_ativa' => false,
                                    'redirect' => 'planos.html'
                                ]);
                                break;
                            }

                            $user['assinatura'] = $assinatura;

                            // Atualizar último acesso
                            $checkColumn = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_acesso'");
                            if ($checkColumn->num_rows > 0) {
                                $conn->query("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = " . $user['id']);
                            }

                            echo json_encode([
                                'success' => true,
                                'message' => 'Login realizado com sucesso!',
                                'user' => $user,
                                'tipo' => 'aluno'
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Email ou senha incorretos.'
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao fazer login: ' . $e->getMessage()
                    ]);
                }
            }
            break;

        case 'register':
            if ($method === 'POST') {
                if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                    throw new Exception('Dados de registro incompletos');
                }

                $name = $data['name'];
                $email = $data['email'];
                $password = $data['password'];

                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? UNION SELECT id FROM professores WHERE email = ?");
                $stmt->bind_param("ss", $email, $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Este email já está cadastrado.'
                    ]);
                    break;
                }

                $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, status) VALUES (?, ?, ?, 'Branca', NOW(), 'Ativo')");
                $stmt->bind_param("sss", $name, $email, $password);

                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cadastro realizado com sucesso!',
                        'userId' => $conn->insert_id
                    ]);
                } else {
                    throw new Exception('Erro ao realizar cadastro.');
                }
            }
            break;

        case 'alunos':
            if ($method === 'GET') {
                try {
                    $result = $conn->query("SELECT DATABASE()");
                    $dbName = $result->fetch_row()[0];
                    error_log("Banco de dados atual: " . $dbName);

                    $tables = $conn->query("SHOW TABLES");
                    error_log("Tabelas encontradas: " . $tables->num_rows);
                    while ($table = $tables->fetch_row()) {
                        error_log("Tabela: " . $table[0]);
                    }

                    $checkTable = $conn->query("SHOW TABLES LIKE 'usuarios'");
                    if ($checkTable->num_rows === 0) {
                        throw new Exception('Tabela usuarios não encontrada');
                    }

                    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE tipo_usuario = 'Aluno' OR tipo_usuario IS NULL ORDER BY nome");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    if (!$result) {
                        throw new Exception('Erro ao obter resultado: ' . $stmt->error);
                    }

                    $alunos = $result->fetch_all(MYSQLI_ASSOC);
                    error_log("Número de alunos encontrados: " . count($alunos));

                    if ($alunos === null) {
                        $alunos = [];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $alunos,
                        'count' => count($alunos)
                    ]);

                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'alunos/add':
            if ($method === 'POST') {
                if (!isset($data['nome']) || !isset($data['email']) || !isset($data['senha'])) {
                    throw new Exception('Dados de cadastro incompletos');
                }

                $nome = $data['nome'];
                $email = $data['email'];
                $senha = $data['senha'];
                $faixa = isset($data['faixa']) ? $data['faixa'] : 'Branca';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se o email já existe
                    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Este email já está cadastrado.');
                    }

                    // Inserir novo aluno
                    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, status, tipo_usuario) VALUES (?, ?, ?, ?, NOW(), ?, 'Aluno')");
                    $stmt->bind_param("sssss", $nome, $email, $senha, $faixa, $status);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir aluno: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Aluno adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^alunos\/(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $aluno = $result->fetch_assoc();

                    if (!$aluno) {
                        throw new Exception('Aluno não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $aluno
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'alunos/update':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['nome']) || !isset($data['email'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $nome = $data['nome'];
                $email = $data['email'];
                $faixa = isset($data['faixa']) ? $data['faixa'] : 'Branca';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se o email já existe para outro usuário
                    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stmt->bind_param("si", $email, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Este email já está sendo usado por outro usuário.');
                    }

                    // Atualizar aluno
                    $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, faixa = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $nome, $email, $faixa, $status, $id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar aluno: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Aluno atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'alunos/status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'alunos/delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ? AND tipo_usuario = 'Aluno'");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Aluno não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Aluno excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'verificar-assinatura':
            if ($method === 'GET') {
                if (!isset($_GET['usuario_id'])) {
                    throw new Exception('ID do usuário não fornecido');
                }

                $usuario_id = $_GET['usuario_id'];

                try {
                    $sql = "SELECT a.*, p.nome as plano_nome, p.valor, p.duracao_meses 
                           FROM assinaturas a 
                           JOIN planos p ON a.plano_id = p.id 
                           WHERE a.usuario_id = ? 
                           AND a.status = 'Ativa' 
                           AND a.data_fim >= NOW() 
                           ORDER BY a.data_fim DESC 
                           LIMIT 1";

                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $usuario_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    $assinatura = $result->fetch_assoc();

                    // Atualizar status do usuário baseado na assinatura
                    $novo_status = !empty($assinatura) ? 'Ativo' : 'Inativo';
                    $stmt = $conn->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $novo_status, $usuario_id);
                    $stmt->execute();

                    echo json_encode([
                        'success' => true,
                        'tem_assinatura' => !empty($assinatura),
                        'assinatura' => $assinatura,
                        'status_usuario' => $novo_status
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'professores':
            if ($method === 'GET') {
                try {
                    $result = $conn->query("SELECT DATABASE()");
                    $dbName = $result->fetch_row()[0];
                    error_log("Banco de dados atual: " . $dbName);

                    $tables = $conn->query("SHOW TABLES");
                    error_log("Tabelas encontradas: " . $tables->num_rows);
                    while ($table = $tables->fetch_row()) {
                        error_log("Tabela: " . $table[0]);
                    }

                    $checkTable = $conn->query("SHOW TABLES LIKE 'professores'");
                    if ($checkTable->num_rows === 0) {
                        throw new Exception('Tabela professores não encontrada');
                    }

                    $stmt = $conn->prepare("SELECT * FROM professores ORDER BY nome");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    if (!$result) {
                        throw new Exception('Erro ao obter resultado: ' . $stmt->error);
                    }

                    $professores = $result->fetch_all(MYSQLI_ASSOC);
                    error_log("Número de professores encontrados: " . count($professores));

                    if ($professores === null) {
                        $professores = [];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $professores,
                        'count' => count($professores)
                    ]);

                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'salvar-cartao':
            if ($method === 'POST') {
                if (!isset($data['usuario_id']) || !isset($data['numero']) || !isset($data['nome_titular']) || !isset($data['data_vencimento']) || !isset($data['cvv']) || !isset($data['bandeira']) || !isset($data['plano_id'])) {
                    throw new Exception('Dados de cartão incompletos');
                }

                $usuario_id = $data['usuario_id'];
                $numero = $data['numero'];
                $nome_titular = $data['nome_titular'];
                $data_vencimento = $data['data_vencimento'];
                $cvv = $data['cvv'];
                $bandeira = $data['bandeira'];
                $plano_id = $data['plano_id'];

                // Buscar informações do plano
                $stmt = $conn->prepare("SELECT valor, duracao_meses FROM planos WHERE id = ?");
                $stmt->bind_param("i", $plano_id);
                $stmt->execute();
                $plano = $stmt->get_result()->fetch_assoc();

                // Calcular data de vencimento da assinatura
                $data_inicio = date('Y-m-d H:i:s');
                $data_vencimento_assinatura = date('Y-m-d H:i:s', strtotime("+{$plano['duracao_meses']} months"));

                $stmt = $conn->prepare("INSERT INTO cartoes (usuario_id, numero, nome_titular, data_vencimento, cvv, bandeira) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $usuario_id, $numero, $nome_titular, $data_vencimento, $cvv, $bandeira);

                if ($stmt->execute()) {
                    // Criar assinatura
                    $stmt = $conn->prepare("INSERT INTO assinaturas (usuario_id, plano_id, data_inicio, data_fim, status) VALUES (?, ?, ?, ?, 'Ativa')");
                    $stmt->bind_param("iiss", $usuario_id, $plano_id, $data_inicio, $data_vencimento_assinatura);
                    $stmt->execute();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Cartão salvo com sucesso!',
                        'cartao_id' => $conn->insert_id,
                        'data_vencimento' => $data_vencimento_assinatura
                    ]);
                } else {
                    throw new Exception('Erro ao salvar cartão');
                }
            }
            break;

        case 'processar-pagamento':
            if ($method === 'POST') {
                if (!isset($data['usuario_id']) || !isset($data['plano_id']) || !isset($data['valor'])) {
                    throw new Exception('Dados de pagamento incompletos');
                }

                try {
                    $conn->begin_transaction();

                    $usuario_id = $data['usuario_id'];
                    $plano_id = $data['plano_id'];
                    $valor = $data['valor'];
                    $metodo_pagamento = $data['metodo_pagamento'] ?? 'Cartão de Crédito';
                    $status_pagamento = $data['status'] ?? 'Pago';

                    // 1. Registrar o pagamento na tabela pagamentos
                    $stmt = $conn->prepare("INSERT INTO pagamentos (usuario_id, valor, data_pagamento, status) VALUES (?, ?, NOW(), ?)");
                    $stmt->bind_param("ids", $usuario_id, $valor, $status_pagamento);
                    $stmt->execute();
                    $pagamento_id = $conn->insert_id;

                    // 2. Se for pagamento com cartão, salvar os dados do cartão
                    if (isset($data['cartao']) && $metodo_pagamento === 'Cartão de Crédito') {
                        $cartao = $data['cartao'];
                        $numero = $cartao['numero'];
                        $nome_titular = $cartao['nome'];
                        $data_vencimento = $cartao['vencimento'];
                        $cvv = $cartao['cvv'];
                        $bandeira = $cartao['bandeira'];

                        $stmt = $conn->prepare("INSERT INTO cartoes (usuario_id, numero, nome_titular, data_vencimento, cvv, bandeira) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssss", $usuario_id, $numero, $nome_titular, $data_vencimento, $cvv, $bandeira);
                        $stmt->execute();
                    }

                    // 3. Buscar informações do plano para calcular a data de fim da assinatura
                    $stmt = $conn->prepare("SELECT duracao_meses FROM planos WHERE id = ?");
                    $stmt->bind_param("i", $plano_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $plano = $result->fetch_assoc();

                    if (!$plano) {
                        throw new Exception('Plano não encontrado');
                    }

                    $duracao_meses = $plano['duracao_meses'];

                    // 4. Calcular datas de início e fim da assinatura
                    $data_inicio = date('Y-m-d H:i:s');
                    $data_fim = date('Y-m-d H:i:s', strtotime("+{$duracao_meses} months"));

                    // 5. Criar a assinatura
                    $stmt = $conn->prepare("INSERT INTO assinaturas (usuario_id, plano_id, data_inicio, data_fim, status) VALUES (?, ?, ?, ?, 'Ativa')");
                    $stmt->bind_param("iiss", $usuario_id, $plano_id, $data_inicio, $data_fim);
                    $stmt->execute();
                    $assinatura_id = $conn->insert_id;

                    // 6. Atualizar status do usuário para Ativo
                    $stmt = $conn->prepare("UPDATE usuarios SET status = 'Ativo' WHERE id = ?");
                    $stmt->bind_param("i", $usuario_id);
                    $stmt->execute();

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Pagamento processado com sucesso!',
                        'pagamento_id' => $pagamento_id,
                        'assinatura_id' => $assinatura_id,
                        'data_inicio' => $data_inicio,
                        'data_fim' => $data_fim
                    ]);

                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
                    ]);
                }
            }
            break;

        // Adicione este case no switch do arquivo api.php
        case 'enviar-notificacao':
            if ($method === 'POST') {
                if (!isset($data['usuario_id']) || !isset($data['titulo']) || !isset($data['mensagem'])) {
                    throw new Exception('Dados da notificação incompletos');
                }

                $usuario_id = $data['usuario_id'];
                $titulo = $data['titulo'];
                $mensagem = $data['mensagem'];

                try {
                    // Verificar se a tabela existe
                    $checkTable = $conn->query("SHOW TABLES LIKE 'notificacoes'");
                    if ($checkTable->num_rows === 0) {
                        // Criar tabela se não existir
                        $conn->query("CREATE TABLE notificacoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    titulo VARCHAR(255) NOT NULL,
                    mensagem TEXT NOT NULL,
                    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
                    lida BOOLEAN DEFAULT FALSE,
                    tipo VARCHAR(50) DEFAULT 'aluno_para_admin'
                )");
                    }

                    // Inserir a notificação
                    $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, titulo, mensagem) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $usuario_id, $titulo, $mensagem);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao salvar notificação: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Mensagem enviada com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes':
            if ($method === 'GET') {
                $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'aluno_para_admin';
                $usuario_id = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : null;
                $admin = isset($_GET['admin']) ? true : false;

                try {
                    // Verificar se a tabela existe
                    $checkTable = $conn->query("SHOW TABLES LIKE 'notificacoes'");
                    if ($checkTable->num_rows === 0) {
                        echo json_encode([
                            'success' => true,
                            'notificacoes' => []
                        ]);
                        break;
                    }

                    // Construir a consulta SQL
                    $sql = "SELECT n.*, u.nome as nome_usuario, u.email as email_usuario 
                   FROM notificacoes n 
                   LEFT JOIN usuarios u ON n.usuario_id = u.id";

                    $params = [];
                    $types = "";

                    // Se for admin, mostrar todas as notificações
                    if (!$admin) {
                        // Se não for admin, mostrar apenas as notificações do usuário
                        $sql .= " WHERE n.usuario_id = ?";
                        $params[] = $usuario_id;
                        $types .= "i";
                    }

                    $sql .= " ORDER BY n.data_envio DESC";

                    $stmt = $conn->prepare($sql);

                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }

                    $stmt->execute();
                    $result = $stmt->get_result();

                    $notificacoes = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'notificacoes' => $notificacoes
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'marcar-notificacao-lida':
            if ($method === 'POST') {
                if (!isset($data['notificacao_id'])) {
                    throw new Exception('ID da notificação não fornecido');
                }

                $notificacao_id = $data['notificacao_id'];

                try {
                    $stmt = $conn->prepare("UPDATE notificacoes SET lida = TRUE WHERE id = ?");
                    $stmt->bind_param("i", $notificacao_id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar notificação: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificação atualizada com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'usuarios/delete-dependencies':
            if ($method === 'POST') {
                if (!isset($data['usuario_id'])) {
                    throw new Exception('ID do usuário não fornecido');
                }

                $usuario_id = $data['usuario_id'];

                try {
                    // Iniciar transação para garantir que todas as operações sejam concluídas ou nenhuma
                    $conn->begin_transaction();

                    // 1. Excluir registros de frequência
                    $stmt = $conn->prepare("DELETE FROM frequencia WHERE usuario_id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query (frequência): ' . $conn->error);
                    }
                    $stmt->bind_param("i", $usuario_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao excluir registros de frequência: ' . $stmt->error);
                    }

                    // 2. Excluir assinaturas
                    $stmt = $conn->prepare("DELETE FROM assinaturas WHERE usuario_id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query (assinaturas): ' . $conn->error);
                    }
                    $stmt->bind_param("i", $usuario_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao excluir assinaturas: ' . $stmt->error);
                    }

                    // 3. Excluir pagamentos
                    $stmt = $conn->prepare("DELETE FROM pagamentos WHERE usuario_id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query (pagamentos): ' . $conn->error);
                    }
                    $stmt->bind_param("i", $usuario_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao excluir pagamentos: ' . $stmt->error);
                    }

                    // 4. Excluir cartões
                    $stmt = $conn->prepare("DELETE FROM cartoes WHERE usuario_id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query (cartões): ' . $conn->error);
                    }
                    $stmt->bind_param("i", $usuario_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao excluir cartões: ' . $stmt->error);
                    }

                    // 5. Excluir notificações
                    $stmt = $conn->prepare("DELETE FROM notificacoes WHERE usuario_id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query (notificações): ' . $conn->error);
                    }
                    $stmt->bind_param("i", $usuario_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao excluir notificações: ' . $stmt->error);
                    }

                    // Confirmar todas as operações
                    $conn->commit();

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    // Reverter todas as operações em caso de erro
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            break;

        case 'professores/delete-dependencies':
            if ($method === 'POST') {
                if (!isset($data['professor_id'])) {
                    throw new Exception('ID do professor não fornecido');
                }

                $professor_id = $data['professor_id'];

                try {
                    // Iniciar transação
                    $conn->begin_transaction();

                    // 1. Atualizar horários para remover referência ao professor
                    $stmt = $conn->prepare("UPDATE horarios SET professor_id = NULL WHERE professor_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $professor_id);
                        $stmt->execute();
                    }

                    // 2. Excluir aulas ministradas pelo professor
                    $stmt = $conn->prepare("DELETE FROM aulas WHERE professor_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $professor_id);
                        $stmt->execute();
                    }

                    // 3. Excluir notificações relacionadas ao professor
                    $stmt = $conn->prepare("DELETE FROM notificacoes WHERE usuario_id = ? OR destinatario_id = ? OR remetente_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("iii", $professor_id, $professor_id, $professor_id);
                        $stmt->execute();
                    }

                    // 4. Atualizar frequências para remover referência ao professor
                    $stmt = $conn->prepare("UPDATE frequencia SET professor_id = NULL WHERE professor_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $professor_id);
                        $stmt->execute();
                    }

                    // 5. Excluir avaliações feitas pelo professor
                    $stmt = $conn->prepare("DELETE FROM avaliacoes WHERE professor_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $professor_id);
                        $stmt->execute();
                    }

                    // Confirmar todas as operações
                    $conn->commit();

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    // Reverter todas as operações em caso de erro
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            break;

        case 'planos':
            if ($method === 'GET') {
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'planos'");
                    if ($checkTable->num_rows === 0) {
                        throw new Exception('Tabela planos não encontrada');
                    }

                    $stmt = $conn->prepare("SELECT * FROM planos ORDER BY valor ASC");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    if (!$result) {
                        throw new Exception('Erro ao obter resultado: ' . $stmt->error);
                    }

                    $planos = $result->fetch_all(MYSQLI_ASSOC);

                    if ($planos === null) {
                        $planos = [];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $planos,
                        'count' => count($planos)
                    ]);

                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos_add':
            if ($method === 'POST') {
                if (!isset($data['nome']) || !isset($data['valor']) || !isset($data['duracao_meses'])) {
                    throw new Exception('Dados de cadastro incompletos');
                }

                $nome = $data['nome'];
                $valor = $data['valor'];
                $duracao_meses = $data['duracao_meses'];
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $beneficios = isset($data['beneficios']) ? $data['beneficios'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    $stmt = $conn->prepare("SELECT id FROM planos WHERE nome = ?");
                    $stmt->bind_param("s", $nome);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe um plano com este nome.');
                    }

                    $stmt = $conn->prepare("INSERT INTO planos (nome, valor, duracao_meses, descricao, beneficios, status, data_criacao) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sdisss", $nome, $valor, $duracao_meses, $descricao, $beneficios, $status);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir plano: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos_update':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['nome']) || !isset($data['valor']) || !isset($data['duracao_meses'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $nome = $data['nome'];
                $valor = $data['valor'];
                $duracao_meses = $data['duracao_meses'];
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $beneficios = isset($data['beneficios']) ? $data['beneficios'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    $stmt = $conn->prepare("SELECT id FROM planos WHERE nome = ? AND id != ?");
                    $stmt->bind_param("si", $nome, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe outro plano com este nome.');
                    }

                    $stmt = $conn->prepare("UPDATE planos SET nome = ?, valor = ?, duracao_meses = ?, descricao = ?, beneficios = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("sdisssi", $nome, $valor, $duracao_meses, $descricao, $beneficios, $status, $id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar plano: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos_status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE planos SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'planos_delete_dependencies':
            if ($method === 'POST') {
                if (!isset($data['plano_id'])) {
                    throw new Exception('ID do plano não fornecido');
                }

                $plano_id = $data['plano_id'];

                try {
                    $conn->begin_transaction();

                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM assinaturas WHERE plano_id = ? AND status = 'Ativa'");
                    $stmt->bind_param("i", $plano_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();

                    if ($result['total'] > 0) {
                        throw new Exception('Não é possível excluir este plano pois existem assinaturas ativas vinculadas a ele.');
                    }

                    $stmt = $conn->prepare("UPDATE assinaturas SET plano_id = NULL WHERE plano_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $plano_id);
                        $stmt->execute();
                    }

                    $conn->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            break;

        case 'planos_delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM planos WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Plano não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^planos_(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("SELECT * FROM planos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $plano = $result->fetch_assoc();

                    if (!$plano) {
                        throw new Exception('Plano não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $plano
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;


        case 'planos-add':
            if ($method === 'POST') {
                if (!isset($data['nome']) || !isset($data['valor']) || !isset($data['duracao_meses'])) {
                    throw new Exception('Dados de cadastro incompletos');
                }

                $nome = $data['nome'];
                $valor = $data['valor'];
                $duracao_meses = $data['duracao_meses'];
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $beneficios = isset($data['beneficios']) ? $data['beneficios'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se já existe um plano com o mesmo nome
                    $stmt = $conn->prepare("SELECT id FROM planos WHERE nome = ?");
                    $stmt->bind_param("s", $nome);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe um plano com este nome.');
                    }

                    // Inserir novo plano
                    $stmt = $conn->prepare("INSERT INTO planos (nome, valor, duracao_meses, descricao, beneficios, status, data_criacao) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sdisss", $nome, $valor, $duracao_meses, $descricao, $beneficios, $status);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir plano: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos-update':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['nome']) || !isset($data['valor']) || !isset($data['duracao_meses'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $nome = $data['nome'];
                $valor = $data['valor'];
                $duracao_meses = $data['duracao_meses'];
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $beneficios = isset($data['beneficios']) ? $data['beneficios'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se já existe outro plano com o mesmo nome
                    $stmt = $conn->prepare("SELECT id FROM planos WHERE nome = ? AND id != ?");
                    $stmt->bind_param("si", $nome, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe outro plano com este nome.');
                    }

                    // Atualizar plano
                    $stmt = $conn->prepare("UPDATE planos SET nome = ?, valor = ?, duracao_meses = ?, descricao = ?, beneficios = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("sdisssi", $nome, $valor, $duracao_meses, $descricao, $beneficios, $status, $id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar plano: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos-status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE planos SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'planos-delete-dependencies':
            if ($method === 'POST') {
                if (!isset($data['plano_id'])) {
                    throw new Exception('ID do plano não fornecido');
                }

                $plano_id = $data['plano_id'];

                try {
                    // Iniciar transação
                    $conn->begin_transaction();

                    // 1. Verificar se existem assinaturas ativas para este plano
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM assinaturas WHERE plano_id = ? AND status = 'Ativa'");
                    $stmt->bind_param("i", $plano_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();

                    if ($result['total'] > 0) {
                        throw new Exception('Não é possível excluir este plano pois existem assinaturas ativas vinculadas a ele.');
                    }

                    // 2. Atualizar assinaturas inativas para remover referência ao plano
                    $stmt = $conn->prepare("UPDATE assinaturas SET plano_id = NULL WHERE plano_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $plano_id);
                        $stmt->execute();
                    }

                    // Confirmar todas as operações
                    $conn->commit();

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    // Reverter todas as operações em caso de erro
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            break;

        case 'planos-delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM planos WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Plano não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos-assinantes':
            if ($method === 'GET') {
                if (!isset($_GET['plano_id'])) {
                    throw new Exception('ID do plano não fornecido');
                }

                $plano_id = $_GET['plano_id'];

                try {
                    $stmt = $conn->prepare("
                        SELECT u.nome, u.email, a.data_inicio, a.data_fim, a.status 
                        FROM assinaturas a 
                        JOIN usuarios u ON a.usuario_id = u.id 
                        WHERE a.plano_id = ? 
                        ORDER BY a.data_inicio DESC
                    ");
                    $stmt->bind_param("i", $plano_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $assinantes = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $assinantes,
                        'count' => count($assinantes)
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^planos-(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("SELECT * FROM planos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $plano = $result->fetch_assoc();

                    if (!$plano) {
                        throw new Exception('Plano não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $plano
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;


        case 'planos/add':
            if ($method === 'POST') {
                if (!isset($data['nome']) || !isset($data['valor']) || !isset($data['duracao_meses'])) {
                    throw new Exception('Dados de cadastro incompletos');
                }

                $nome = $data['nome'];
                $valor = $data['valor'];
                $duracao_meses = $data['duracao_meses'];
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $beneficios = isset($data['beneficios']) ? $data['beneficios'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se já existe um plano com o mesmo nome
                    $stmt = $conn->prepare("SELECT id FROM planos WHERE nome = ?");
                    $stmt->bind_param("s", $nome);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe um plano com este nome.');
                    }

                    // Inserir novo plano
                    $stmt = $conn->prepare("INSERT INTO planos (nome, valor, duracao_meses, descricao, beneficios, status, data_criacao) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sdisss", $nome, $valor, $duracao_meses, $descricao, $beneficios, $status);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir plano: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^planos\/(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("SELECT * FROM planos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $plano = $result->fetch_assoc();

                    if (!$plano) {
                        throw new Exception('Plano não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $plano
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos/update':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['nome']) || !isset($data['valor']) || !isset($data['duracao_meses'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $nome = $data['nome'];
                $valor = $data['valor'];
                $duracao_meses = $data['duracao_meses'];
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $beneficios = isset($data['beneficios']) ? $data['beneficios'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se já existe outro plano com o mesmo nome
                    $stmt = $conn->prepare("SELECT id FROM planos WHERE nome = ? AND id != ?");
                    $stmt->bind_param("si", $nome, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe outro plano com este nome.');
                    }

                    // Atualizar plano
                    $stmt = $conn->prepare("UPDATE planos SET nome = ?, valor = ?, duracao_meses = ?, descricao = ?, beneficios = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("sdisssi", $nome, $valor, $duracao_meses, $descricao, $beneficios, $status, $id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar plano: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos/status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE planos SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'planos/delete-dependencies':
            if ($method === 'POST') {
                if (!isset($data['plano_id'])) {
                    throw new Exception('ID do plano não fornecido');
                }

                $plano_id = $data['plano_id'];

                try {
                    // Iniciar transação
                    $conn->begin_transaction();

                    // 1. Verificar se existem assinaturas ativas para este plano
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM assinaturas WHERE plano_id = ? AND status = 'Ativa'");
                    $stmt->bind_param("i", $plano_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();

                    if ($result['total'] > 0) {
                        throw new Exception('Não é possível excluir este plano pois existem assinaturas ativas vinculadas a ele.');
                    }

                    // 2. Atualizar assinaturas inativas para remover referência ao plano
                    $stmt = $conn->prepare("UPDATE assinaturas SET plano_id = NULL WHERE plano_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $plano_id);
                        $stmt->execute();
                    }

                    // 3. Excluir histórico de pagamentos relacionados ao plano (se houver tabela específica)
                    $stmt = $conn->prepare("DELETE FROM historico_planos WHERE plano_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $plano_id);
                        $stmt->execute();
                    }

                    // Confirmar todas as operações
                    $conn->commit();

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    // Reverter todas as operações em caso de erro
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            break;

        case 'planos/delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM planos WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Plano não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Plano excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'planos/assinantes':
            if ($method === 'GET') {
                if (!isset($_GET['plano_id'])) {
                    throw new Exception('ID do plano não fornecido');
                }

                $plano_id = $_GET['plano_id'];

                try {
                    $stmt = $conn->prepare("
                        SELECT u.nome, u.email, a.data_inicio, a.data_fim, a.status 
                        FROM assinaturas a 
                        JOIN usuarios u ON a.usuario_id = u.id 
                        WHERE a.plano_id = ? 
                        ORDER BY a.data_inicio DESC
                    ");
                    $stmt->bind_param("i", $plano_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $assinantes = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $assinantes,
                        'count' => count($assinantes)
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;



        case 'professores/add':
            if ($method === 'POST') {
                if (!isset($data['nome']) || !isset($data['email']) || !isset($data['senha'])) {
                    throw new Exception('Dados de cadastro incompletos');
                }

                $nome = $data['nome'];
                $email = $data['email'];
                $senha = $data['senha'];
                $faixa = isset($data['faixa']) ? $data['faixa'] : 'Branca';
                $grau = isset($data['grau']) ? $data['grau'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se o email já existe
                    $stmt = $conn->prepare("SELECT id FROM professores WHERE email = ? UNION SELECT id FROM usuarios WHERE email = ?");
                    $stmt->bind_param("ss", $email, $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Este email já está cadastrado.');
                    }

                    // Inserir novo professor
                    $stmt = $conn->prepare("INSERT INTO professores (nome, email, senha, faixa, grau, data_cadastro, status) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                    $stmt->bind_param("ssssss", $nome, $email, $senha, $faixa, $grau, $status);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir professor: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Professor adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^professores\/(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("SELECT * FROM professores WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $professor = $result->fetch_assoc();

                    if (!$professor) {
                        throw new Exception('Professor não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $professor
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'professores/update':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['nome']) || !isset($data['email'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $nome = $data['nome'];
                $email = $data['email'];
                $faixa = isset($data['faixa']) ? $data['faixa'] : 'Branca';
                $grau = isset($data['grau']) ? $data['grau'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar se o email já existe para outro usuário
                    $stmt = $conn->prepare("SELECT id FROM professores WHERE email = ? AND id != ? UNION SELECT id FROM usuarios WHERE email = ?");
                    $stmt->bind_param("sis", $email, $id, $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Este email já está sendo usado por outro usuário.');
                    }

                    // Atualizar professor
                    $stmt = $conn->prepare("UPDATE professores SET nome = ?, email = ?, faixa = ?, grau = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $nome, $email, $faixa, $grau, $status, $id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar professor: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Professor atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios':
            if ($method === 'GET') {
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'horarios'");
                    if ($checkTable->num_rows === 0) {
                        throw new Exception('Tabela horarios não encontrada');
                    }

                    // Verificar estrutura da tabela
                    $columns = $conn->query("SHOW COLUMNS FROM horarios");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }

                    error_log("Colunas da tabela horarios: " . implode(', ', $columnNames));

                    // Query simples para começar
                    $stmt = $conn->prepare("SELECT * FROM horarios ORDER BY id");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    if (!$result) {
                        throw new Exception('Erro ao obter resultado: ' . $stmt->error);
                    }

                    $horarios = $result->fetch_all(MYSQLI_ASSOC);

                    if ($horarios === null) {
                        $horarios = [];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $horarios,
                        'count' => count($horarios),
                        'columns' => $columnNames
                    ]);

                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos':
            if ($method === 'GET') {
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'pagamentos'");
                    if ($checkTable->num_rows === 0) {
                        throw new Exception('Tabela pagamentos não encontrada');
                    }

                    // Verificar estrutura da tabela
                    $columns = $conn->query("SHOW COLUMNS FROM pagamentos");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }

                    error_log("Colunas da tabela pagamentos: " . implode(', ', $columnNames));

                    // Query adaptativa baseada nas colunas existentes
                    $sql = "SELECT p.*";

                    // Adicionar informações do usuário se possível
                    if (in_array('usuario_id', $columnNames)) {
                        $sql .= ", u.nome as usuario_nome, u.email as usuario_email";
                        $sql .= " FROM pagamentos p LEFT JOIN usuarios u ON p.usuario_id = u.id";
                    } else {
                        $sql .= " FROM pagamentos p";
                    }

                    // Adicionar informações do plano se possível
                    if (in_array('plano_id', $columnNames)) {
                        $sql = str_replace("FROM pagamentos p", "FROM pagamentos p LEFT JOIN planos pl ON p.plano_id = pl.id", $sql);
                        $sql = str_replace("SELECT p.*", "SELECT p.*, pl.nome as plano_nome", $sql);
                    }

                    // Ordenar por data mais recente
                    if (in_array('data_pagamento', $columnNames)) {
                        $sql .= " ORDER BY p.data_pagamento DESC";
                    } else {
                        $sql .= " ORDER BY p.id DESC";
                    }

                    error_log("Query SQL gerada: " . $sql);

                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    if (!$result) {
                        throw new Exception('Erro ao obter resultado: ' . $stmt->error);
                    }

                    $pagamentos = $result->fetch_all(MYSQLI_ASSOC);

                    if ($pagamentos === null) {
                        $pagamentos = [];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $pagamentos,
                        'count' => count($pagamentos),
                        'columns' => $columnNames
                    ]);

                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_add':
            if ($method === 'POST') {
                try {
                    // Verificar estrutura da tabela primeiro
                    $columns = $conn->query("SHOW COLUMNS FROM pagamentos");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }

                    // Campos obrigatórios básicos
                    if (!isset($data['usuario_id']) || !isset($data['valor'])) {
                        throw new Exception('Dados de pagamento incompletos (usuario_id e valor são obrigatórios)');
                    }

                    $requiredFields = [];
                    $values = [];
                    $types = "";
                    $placeholders = [];

                    // Construir inserção baseada nas colunas disponíveis
                    if (in_array('usuario_id', $columnNames)) {
                        $requiredFields[] = 'usuario_id';
                        $values[] = $data['usuario_id'];
                        $types .= "i";
                        $placeholders[] = "?";
                    }

                    if (in_array('valor', $columnNames)) {
                        $requiredFields[] = 'valor';
                        $values[] = $data['valor'];
                        $types .= "d";
                        $placeholders[] = "?";
                    }

                    if (in_array('plano_id', $columnNames) && isset($data['plano_id']) && !empty($data['plano_id'])) {
                        $requiredFields[] = 'plano_id';
                        $values[] = $data['plano_id'];
                        $types .= "i";
                        $placeholders[] = "?";
                    }

                    if (in_array('metodo_pagamento', $columnNames)) {
                        $requiredFields[] = 'metodo_pagamento';
                        $values[] = isset($data['metodo_pagamento']) ? $data['metodo_pagamento'] : 'Cartão de Crédito';
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('status', $columnNames)) {
                        $requiredFields[] = 'status';
                        $values[] = isset($data['status']) ? $data['status'] : 'Pago';
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('data_pagamento', $columnNames)) {
                        $requiredFields[] = 'data_pagamento';
                        $values[] = isset($data['data_pagamento']) ? $data['data_pagamento'] : date('Y-m-d H:i:s');
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('observacoes', $columnNames) && isset($data['observacoes'])) {
                        $requiredFields[] = 'observacoes';
                        $values[] = $data['observacoes'];
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (empty($requiredFields)) {
                        throw new Exception('Nenhum campo válido fornecido para inserção');
                    }

                    // Construir e executar query
                    $sql = "INSERT INTO pagamentos (" . implode(', ', $requiredFields) . ") VALUES (" . implode(', ', $placeholders) . ")";

                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param($types, ...$values);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir pagamento: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Pagamento adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_update':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('ID do pagamento não fornecido');
                }

                try {
                    // Verificar estrutura da tabela
                    $columns = $conn->query("SHOW COLUMNS FROM pagamentos");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }

                    $id = $data['id'];
                    $updateFields = [];
                    $values = [];
                    $types = "";

                    // Construir atualização baseada nas colunas disponíveis
                    if (in_array('valor', $columnNames) && isset($data['valor'])) {
                        $updateFields[] = 'valor = ?';
                        $values[] = $data['valor'];
                        $types .= "d";
                    }

                    if (in_array('status', $columnNames) && isset($data['status'])) {
                        $updateFields[] = 'status = ?';
                        $values[] = $data['status'];
                        $types .= "s";
                    }

                    if (in_array('metodo_pagamento', $columnNames) && isset($data['metodo_pagamento'])) {
                        $updateFields[] = 'metodo_pagamento = ?';
                        $values[] = $data['metodo_pagamento'];
                        $types .= "s";
                    }

                    if (in_array('observacoes', $columnNames) && isset($data['observacoes'])) {
                        $updateFields[] = 'observacoes = ?';
                        $values[] = $data['observacoes'];
                        $types .= "s";
                    }

                    if (in_array('data_pagamento', $columnNames) && isset($data['data_pagamento'])) {
                        $updateFields[] = 'data_pagamento = ?';
                        $values[] = $data['data_pagamento'];
                        $types .= "s";
                    }

                    if (empty($updateFields)) {
                        throw new Exception('Nenhum campo válido fornecido para atualização');
                    }

                    // Adicionar ID no final
                    $values[] = $id;
                    $types .= "i";

                    $sql = "UPDATE pagamentos SET " . implode(', ', $updateFields) . " WHERE id = ?";

                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param($types, ...$values);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar pagamento: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Pagamento atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE pagamentos SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'pagamentos_delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM pagamentos WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Pagamento não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Pagamento excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_estatisticas':
            if ($method === 'GET') {
                try {
                    $stats = [];

                    // Total de pagamentos
                    $result = $conn->query("SELECT COUNT(*) as total FROM pagamentos");
                    $stats['total_pagamentos'] = $result->fetch_assoc()['total'];

                    // Total arrecadado
                    $result = $conn->query("SELECT SUM(valor) as total FROM pagamentos WHERE status = 'Pago'");
                    $stats['total_arrecadado'] = $result->fetch_assoc()['total'] ?? 0;

                    // Pagamentos por status
                    $result = $conn->query("
                        SELECT status, COUNT(*) as total, SUM(valor) as valor_total 
                        FROM pagamentos 
                        GROUP BY status
                    ");
                    $stats['por_status'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Pagamentos por método
                    $result = $conn->query("
                        SELECT metodo_pagamento, COUNT(*) as total, SUM(valor) as valor_total 
                        FROM pagamentos 
                        WHERE status = 'Pago'
                        GROUP BY metodo_pagamento
                    ");
                    $stats['por_metodo'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Pagamentos dos últimos 30 dias
                    $result = $conn->query("
                        SELECT DATE(data_pagamento) as data, COUNT(*) as total, SUM(valor) as valor_total
                        FROM pagamentos 
                        WHERE data_pagamento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        AND status = 'Pago'
                        GROUP BY DATE(data_pagamento)
                        ORDER BY data DESC
                    ");
                    $stats['ultimos_30_dias'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Top 5 usuários que mais pagaram
                    $result = $conn->query("
                        SELECT u.nome, u.email, COUNT(p.id) as total_pagamentos, SUM(p.valor) as valor_total
                        FROM pagamentos p
                        JOIN usuarios u ON p.usuario_id = u.id
                        WHERE p.status = 'Pago'
                        GROUP BY p.usuario_id
                        ORDER BY valor_total DESC
                        LIMIT 5
                    ");
                    $stats['top_usuarios'] = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $stats
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_relatorio':
            if ($method === 'GET') {
                try {
                    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
                    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');
                    $status = isset($_GET['status']) ? $_GET['status'] : '';
                    $metodo = isset($_GET['metodo']) ? $_GET['metodo'] : '';

                    $sql = "
                        SELECT p.*, u.nome as usuario_nome, u.email as usuario_email, pl.nome as plano_nome
                        FROM pagamentos p
                        LEFT JOIN usuarios u ON p.usuario_id = u.id
                        LEFT JOIN planos pl ON p.plano_id = pl.id
                        WHERE DATE(p.data_pagamento) BETWEEN ? AND ?
                    ";

                    $params = [$data_inicio, $data_fim];
                    $types = "ss";

                    if (!empty($status)) {
                        $sql .= " AND p.status = ?";
                        $params[] = $status;
                        $types .= "s";
                    }

                    if (!empty($metodo)) {
                        $sql .= " AND p.metodo_pagamento = ?";
                        $params[] = $metodo;
                        $types .= "s";
                    }

                    $sql .= " ORDER BY p.data_pagamento DESC";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $pagamentos = $result->fetch_all(MYSQLI_ASSOC);

                    // Calcular totais
                    $total_geral = 0;
                    $total_pago = 0;
                    $total_pendente = 0;

                    foreach ($pagamentos as $pagamento) {
                        $total_geral += $pagamento['valor'];
                        if ($pagamento['status'] === 'Pago') {
                            $total_pago += $pagamento['valor'];
                        } else {
                            $total_pendente += $pagamento['valor'];
                        }
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $pagamentos,
                        'resumo' => [
                            'total_registros' => count($pagamentos),
                            'total_geral' => $total_geral,
                            'total_pago' => $total_pago,
                            'total_pendente' => $total_pendente,
                            'periodo' => [
                                'inicio' => $data_inicio,
                                'fim' => $data_fim
                            ]
                        ]
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_usuarios_disponiveis':
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT id, nome, email, faixa 
                        FROM usuarios 
                        WHERE status = 'Ativo' 
                        ORDER BY nome
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $usuarios = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $usuarios
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_planos_disponiveis':
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT id, nome, valor, duracao_meses 
                        FROM planos 
                        WHERE status = 'Ativo' 
                        ORDER BY valor
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $planos = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $planos
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_estornar':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['motivo'])) {
                    throw new Exception('Dados de estorno incompletos');
                }

                $id = $data['id'];
                $motivo = $data['motivo'];

                try {
                    $conn->begin_transaction();

                    // Verificar se o pagamento existe e está pago
                    $stmt = $conn->prepare("SELECT * FROM pagamentos WHERE id = ? AND status = 'Pago'");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $pagamento = $stmt->get_result()->fetch_assoc();

                    if (!$pagamento) {
                        throw new Exception('Pagamento não encontrado ou não pode ser estornado');
                    }

                    // Atualizar status do pagamento
                    $stmt = $conn->prepare("UPDATE pagamentos SET status = 'Estornado', observacoes = CONCAT(COALESCE(observacoes, ''), ' - ESTORNO: ', ?) WHERE id = ?");
                    $stmt->bind_param("si", $motivo, $id);
                    $stmt->execute();

                    // Se houver assinatura relacionada, cancelar
                    if (!empty($pagamento['usuario_id'])) {
                        $stmt = $conn->prepare("UPDATE assinaturas SET status = 'Cancelada' WHERE usuario_id = ? AND status = 'Ativa'");
                        $stmt->bind_param("i", $pagamento['usuario_id']);
                        $stmt->execute();

                        // Atualizar status do usuário
                        $stmt = $conn->prepare("UPDATE usuarios SET status = 'Inativo' WHERE id = ?");
                        $stmt->bind_param("i", $pagamento['usuario_id']);
                        $stmt->execute();
                    }

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Pagamento estornado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'pagamentos_reprocessar':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('ID do pagamento não fornecido');
                }

                $id = $data['id'];

                try {
                    $conn->begin_transaction();

                    // Verificar se o pagamento existe
                    $stmt = $conn->prepare("SELECT * FROM pagamentos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $pagamento = $stmt->get_result()->fetch_assoc();

                    if (!$pagamento) {
                        throw new Exception('Pagamento não encontrado');
                    }

                    // Atualizar status do pagamento
                    $stmt = $conn->prepare("UPDATE pagamentos SET status = 'Pago', data_pagamento = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    // Se houver usuário relacionado, reativar assinatura
                    if (!empty($pagamento['usuario_id']) && !empty($pagamento['plano_id'])) {
                        // Buscar informações do plano
                        $stmt = $conn->prepare("SELECT duracao_meses FROM planos WHERE id = ?");
                        $stmt->bind_param("i", $pagamento['plano_id']);
                        $stmt->execute();
                        $plano = $stmt->get_result()->fetch_assoc();

                        if ($plano) {
                            $data_inicio = date('Y-m-d H:i:s');
                            $data_fim = date('Y-m-d H:i:s', strtotime("+{$plano['duracao_meses']} months"));

                            // Criar nova assinatura
                            $stmt = $conn->prepare("INSERT INTO assinaturas (usuario_id, plano_id, data_inicio, data_fim, status) VALUES (?, ?, ?, ?, 'Ativa')");
                            $stmt->bind_param("iiss", $pagamento['usuario_id'], $pagamento['plano_id'], $data_inicio, $data_fim);
                            $stmt->execute();

                            // Reativar usuário
                            $stmt = $conn->prepare("UPDATE usuarios SET status = 'Ativo' WHERE id = ?");
                            $stmt->bind_param("i", $pagamento['usuario_id']);
                            $stmt->execute();
                        }
                    }

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Pagamento reprocessado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^pagamentos_(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT p.*, u.nome as usuario_nome, u.email as usuario_email, pl.nome as plano_nome
                        FROM pagamentos p
                        LEFT JOIN usuarios u ON p.usuario_id = u.id
                        LEFT JOIN planos pl ON p.plano_id = pl.id
                        WHERE p.id = ?
                    ");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $pagamento = $result->fetch_assoc();

                    if (!$pagamento) {
                        throw new Exception('Pagamento não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $pagamento
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

                case 'notificacoes_admin':
            if ($method === 'GET') {
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'notificacoes'");
                    if ($checkTable->num_rows === 0) {
                        throw new Exception('Tabela notificacoes não encontrada');
                    }

                    // Verificar estrutura da tabela
                    $columns = $conn->query("SHOW COLUMNS FROM notificacoes");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }
                    
                    error_log("Colunas da tabela notificacoes: " . implode(', ', $columnNames));

                    // Filtros opcionais
                    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
                    $status = isset($_GET['status']) ? $_GET['status'] : '';
                    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
                    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

                    // Query adaptativa baseada nas colunas existentes
                    $sql = "SELECT n.*";
                    
                    // Adicionar informações do usuário se possível
                    if (in_array('usuario_id', $columnNames)) {
                        $sql .= ", u.nome as usuario_nome, u.email as usuario_email, u.faixa as usuario_faixa";
                        $sql .= " FROM notificacoes n LEFT JOIN usuarios u ON n.usuario_id = u.id";
                    } else {
                        $sql .= " FROM notificacoes n";
                    }

                    // Construir WHERE baseado nos filtros
                    $whereConditions = [];
                    $params = [];
                    $types = "";

                    if (!empty($tipo) && in_array('tipo', $columnNames)) {
                        $whereConditions[] = "n.tipo = ?";
                        $params[] = $tipo;
                        $types .= "s";
                    }

                    if (!empty($status) && in_array('lida', $columnNames)) {
                        $lida = ($status === 'lida') ? 1 : 0;
                        $whereConditions[] = "n.lida = ?";
                        $params[] = $lida;
                        $types .= "i";
                    }

                    if (!empty($data_inicio) && in_array('data_envio', $columnNames)) {
                        $whereConditions[] = "DATE(n.data_envio) >= ?";
                        $params[] = $data_inicio;
                        $types .= "s";
                    }

                    if (!empty($data_fim) && in_array('data_envio', $columnNames)) {
                        $whereConditions[] = "DATE(n.data_envio) <= ?";
                        $params[] = $data_fim;
                        $types .= "s";
                    }

                    if (!empty($whereConditions)) {
                        $sql .= " WHERE " . implode(" AND ", $whereConditions);
                    }
                    
                    // Ordenar por data mais recente
                    if (in_array('data_envio', $columnNames)) {
                        $sql .= " ORDER BY n.data_envio DESC";
                    } else {
                        $sql .= " ORDER BY n.id DESC";
                    }

                    error_log("Query SQL gerada: " . $sql);

                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $result = $stmt->get_result();
                    if (!$result) {
                        throw new Exception('Erro ao obter resultado: ' . $stmt->error);
                    }

                    $notificacoes = $result->fetch_all(MYSQLI_ASSOC);

                    if ($notificacoes === null) {
                        $notificacoes = [];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $notificacoes,
                        'count' => count($notificacoes),
                        'columns' => $columnNames
                    ]);

                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_add':
            if ($method === 'POST') {
                try {
                    // Verificar estrutura da tabela primeiro
                    $columns = $conn->query("SHOW COLUMNS FROM notificacoes");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }

                    // Campos obrigatórios básicos
                    if (!isset($data['titulo']) || !isset($data['mensagem'])) {
                        throw new Exception('Dados de notificação incompletos (titulo e mensagem são obrigatórios)');
                    }

                    $requiredFields = [];
                    $values = [];
                    $types = "";
                    $placeholders = [];

                    // Construir inserção baseada nas colunas disponíveis
                    if (in_array('usuario_id', $columnNames) && isset($data['usuario_id']) && !empty($data['usuario_id'])) {
                        $requiredFields[] = 'usuario_id';
                        $values[] = $data['usuario_id'];
                        $types .= "i";
                        $placeholders[] = "?";
                    }

                    if (in_array('titulo', $columnNames)) {
                        $requiredFields[] = 'titulo';
                        $values[] = $data['titulo'];
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('mensagem', $columnNames)) {
                        $requiredFields[] = 'mensagem';
                        $values[] = $data['mensagem'];
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('tipo', $columnNames)) {
                        $requiredFields[] = 'tipo';
                        $values[] = isset($data['tipo']) ? $data['tipo'] : 'admin_para_aluno';
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('data_envio', $columnNames)) {
                        $requiredFields[] = 'data_envio';
                        $values[] = date('Y-m-d H:i:s');
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (in_array('lida', $columnNames)) {
                        $requiredFields[] = 'lida';
                        $values[] = 0;
                        $types .= "i";
                        $placeholders[] = "?";
                    }

                    if (in_array('prioridade', $columnNames) && isset($data['prioridade'])) {
                        $requiredFields[] = 'prioridade';
                        $values[] = $data['prioridade'];
                        $types .= "s";
                        $placeholders[] = "?";
                    }

                    if (empty($requiredFields)) {
                        throw new Exception('Nenhum campo válido fornecido para inserção');
                    }

                    // Construir e executar query
                    $sql = "INSERT INTO notificacoes (" . implode(', ', $requiredFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param($types, ...$values);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir notificação: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificação enviada com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_broadcast':
            if ($method === 'POST') {
                if (!isset($data['titulo']) || !isset($data['mensagem'])) {
                    throw new Exception('Dados de notificação incompletos');
                }

                try {
                    $conn->begin_transaction();

                    $titulo = $data['titulo'];
                    $mensagem = $data['mensagem'];
                    $tipo = isset($data['tipo']) ? $data['tipo'] : 'admin_para_todos';
                    $prioridade = isset($data['prioridade']) ? $data['prioridade'] : 'normal';

                    // Buscar todos os usuários ativos
                    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE status = 'Ativo'");
                    $stmt->execute();
                    $usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                    $notificacoes_enviadas = 0;

                    // Enviar notificação para cada usuário
                    foreach ($usuarios as $usuario) {
                        $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, prioridade, data_envio, lida) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
                        $stmt->bind_param("issss", $usuario['id'], $titulo, $mensagem, $tipo, $prioridade);
                        
                        if ($stmt->execute()) {
                            $notificacoes_enviadas++;
                        }
                    }

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => "Notificação enviada para {$notificacoes_enviadas} usuários!",
                        'total_enviadas' => $notificacoes_enviadas
                    ]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_update':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('ID da notificação não fornecido');
                }

                try {
                    // Verificar estrutura da tabela
                    $columns = $conn->query("SHOW COLUMNS FROM notificacoes");
                    $columnNames = [];
                    while ($column = $columns->fetch_assoc()) {
                        $columnNames[] = $column['Field'];
                    }

                    $id = $data['id'];
                    $updateFields = [];
                    $values = [];
                    $types = "";

                    // Construir atualização baseada nas colunas disponíveis
                    if (in_array('titulo', $columnNames) && isset($data['titulo'])) {
                        $updateFields[] = 'titulo = ?';
                        $values[] = $data['titulo'];
                        $types .= "s";
                    }

                    if (in_array('mensagem', $columnNames) && isset($data['mensagem'])) {
                        $updateFields[] = 'mensagem = ?';
                        $values[] = $data['mensagem'];
                        $types .= "s";
                    }

                    if (in_array('lida', $columnNames) && isset($data['lida'])) {
                        $updateFields[] = 'lida = ?';
                        $values[] = $data['lida'] ? 1 : 0;
                        $types .= "i";
                    }

                    if (in_array('prioridade', $columnNames) && isset($data['prioridade'])) {
                        $updateFields[] = 'prioridade = ?';
                        $values[] = $data['prioridade'];
                        $types .= "s";
                    }

                    if (empty($updateFields)) {
                        throw new Exception('Nenhum campo válido fornecido para atualização');
                    }

                    // Adicionar ID no final
                    $values[] = $id;
                    $types .= "i";

                    $sql = "UPDATE notificacoes SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param($types, ...$values);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar notificação: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificação atualizada com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_marcar_lida':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('ID da notificação não fornecido');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao marcar como lida: ' . $e->getMessage()]);
                }
            }
            break;

        case 'notificacoes_marcar_todas_lidas':
            if ($method === 'POST') {
                try {
                    $usuario_id = isset($data['usuario_id']) ? $data['usuario_id'] : null;
                    
                    if ($usuario_id) {
                        $stmt = $conn->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_id = ?");
                        $stmt->bind_param("i", $usuario_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE notificacoes SET lida = 1");
                    }
                                        $stmt->execute();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Todas as notificações foram marcadas como lidas!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Erro ao marcar notificações como lidas: ' . $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM notificacoes WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Notificação não encontrada ou não pode ser excluída');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificação excluída com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_delete_multiplas':
            if ($method === 'POST') {
                if (!isset($data['ids']) || !is_array($data['ids'])) {
                    throw new Exception('IDs das notificações não fornecidos');
                }

                $ids = $data['ids'];

                try {
                    $conn->begin_transaction();

                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    $sql = "DELETE FROM notificacoes WHERE id IN ($placeholders)";
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $types = str_repeat('i', count($ids));
                    $stmt->bind_param($types, ...$ids);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    $deletadas = $stmt->affected_rows;
                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => "{$deletadas} notificações excluídas com sucesso"
                    ]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_estatisticas':
            if ($method === 'GET') {
                try {
                    $stats = [];

                    // Total de notificações
                    $result = $conn->query("SELECT COUNT(*) as total FROM notificacoes");
                    $stats['total_notificacoes'] = $result->fetch_assoc()['total'];

                    // Notificações não lidas
                    $result = $conn->query("SELECT COUNT(*) as total FROM notificacoes WHERE lida = 0");
                    $stats['nao_lidas'] = $result->fetch_assoc()['total'];

                    // Notificações por tipo
                    $result = $conn->query("
                        SELECT tipo, COUNT(*) as total 
                        FROM notificacoes 
                        GROUP BY tipo
                    ");
                    $stats['por_tipo'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Notificações por prioridade
                    $result = $conn->query("
                        SELECT prioridade, COUNT(*) as total 
                        FROM notificacoes 
                        WHERE prioridade IS NOT NULL
                        GROUP BY prioridade
                    ");
                    $stats['por_prioridade'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Notificações dos últimos 7 dias
                    $result = $conn->query("
                        SELECT DATE(data_envio) as data, COUNT(*) as total
                        FROM notificacoes 
                        WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY DATE(data_envio)
                        ORDER BY data DESC
                    ");
                    $stats['ultimos_7_dias'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Usuários com mais notificações não lidas
                    $result = $conn->query("
                        SELECT u.nome, u.email, COUNT(n.id) as total_nao_lidas
                        FROM notificacoes n
                        JOIN usuarios u ON n.usuario_id = u.id
                        WHERE n.lida = 0
                        GROUP BY n.usuario_id
                        ORDER BY total_nao_lidas DESC
                        LIMIT 10
                    ");
                    $stats['usuarios_mais_nao_lidas'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Taxa de leitura (últimos 30 dias)
                    $result = $conn->query("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN lida = 1 THEN 1 ELSE 0 END) as lidas,
                            ROUND((SUM(CASE WHEN lida = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as taxa_leitura
                        FROM notificacoes 
                        WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ");
                    $stats['taxa_leitura'] = $result->fetch_assoc();

                    echo json_encode([
                        'success' => true,
                        'data' => $stats
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_templates':
            if ($method === 'GET') {
                try {
                    $templates = [
                        [
                            'id' => 'boas_vindas',
                            'nome' => 'Boas-vindas',
                            'titulo' => 'Bem-vindo à Bushido Academy!',
                            'mensagem' => 'Olá! Seja bem-vindo à nossa academia. Estamos muito felizes em tê-lo conosco. Aproveite todos os nossos recursos e não hesite em entrar em contato se precisar de ajuda.',
                            'tipo' => 'admin_para_aluno'
                        ],
                        [
                            'id' => 'pagamento_confirmado',
                            'nome' => 'Pagamento Confirmado',
                            'titulo' => 'Pagamento Confirmado',
                            'mensagem' => 'Seu pagamento foi confirmado com sucesso! Sua assinatura está ativa e você já pode aproveitar todos os benefícios da academia.',
                            'tipo' => 'admin_para_aluno'
                        ],
                        [
                            'id' => 'assinatura_vencendo',
                            'nome' => 'Assinatura Vencendo',
                            'titulo' => 'Sua assinatura vence em breve',
                            'mensagem' => 'Sua assinatura vencerá em poucos dias. Renove agora para continuar aproveitando todos os benefícios da academia sem interrupções.',
                            'tipo' => 'admin_para_aluno'
                        ],
                        [
                            'id' => 'nova_aula',
                            'nome' => 'Nova Aula Disponível',
                            'titulo' => 'Nova aula disponível!',
                            'mensagem' => 'Uma nova aula foi adicionada aos horários. Confira a programação e não perca essa oportunidade de treinar!',
                            'tipo' => 'admin_para_todos'
                        ],
                        [
                            'id' => 'manutencao',
                            'nome' => 'Manutenção do Sistema',
                            'titulo' => 'Manutenção Programada',
                            'mensagem' => 'Informamos que haverá uma manutenção programada no sistema. Durante este período, alguns serviços podem ficar temporariamente indisponíveis.',
                            'tipo' => 'admin_para_todos'
                        ]
                    ];

                    echo json_encode([
                        'success' => true,
                        'data' => $templates
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_usuarios_disponiveis':
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT id, nome, email, faixa, status 
                        FROM usuarios 
                        ORDER BY nome
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $usuarios = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $usuarios
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'notificacoes_limpar_antigas':
            if ($method === 'POST') {
                try {
                    $dias = isset($data['dias']) ? $data['dias'] : 30;
                    $apenas_lidas = isset($data['apenas_lidas']) ? $data['apenas_lidas'] : true;

                    $sql = "DELETE FROM notificacoes WHERE data_envio < DATE_SUB(NOW(), INTERVAL ? DAY)";
                    $params = [$dias];
                    $types = "i";

                    if ($apenas_lidas) {
                        $sql .= " AND lida = 1";
                    }

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();

                    $deletadas = $stmt->affected_rows;

                    echo json_encode([
                        'success' => true,
                        'message' => "{$deletadas} notificações antigas foram removidas",
                        'deletadas' => $deletadas
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

                case 'dashboard_stats':
            if ($method === 'GET') {
                try {
                    $stats = [];

                    // Total de alunos
                    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'Aluno' OR tipo_usuario IS NULL");
                    $stats['total_alunos'] = $result->fetch_assoc()['total'];

                    // Alunos ativos
                    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE (tipo_usuario = 'Aluno' OR tipo_usuario IS NULL) AND status = 'Ativo'");
                    $stats['alunos_ativos'] = $result->fetch_assoc()['total'];

                    // Total de professores
                    $result = $conn->query("SELECT COUNT(*) as total FROM professores");
                    $stats['total_professores'] = $result->fetch_assoc()['total'];

                    // Professores ativos
                    $result = $conn->query("SELECT COUNT(*) as total FROM professores WHERE status = 'Ativo'");
                    $stats['professores_ativos'] = $result->fetch_assoc()['total'];

                    // Total de assinaturas ativas
                    $result = $conn->query("SELECT COUNT(*) as total FROM assinaturas WHERE status = 'Ativa' AND data_fim >= NOW()");
                    $stats['assinaturas_ativas'] = $result->fetch_assoc()['total'];

                    // Total de assinaturas vencidas
                    $result = $conn->query("SELECT COUNT(*) as total FROM assinaturas WHERE status = 'Ativa' AND data_fim < NOW()");
                    $stats['assinaturas_vencidas'] = $result->fetch_assoc()['total'];

                    // Receita do mês atual
                    $result = $conn->query("SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos WHERE status = 'Pago' AND MONTH(data_pagamento) = MONTH(NOW()) AND YEAR(data_pagamento) = YEAR(NOW())");
                    $stats['receita_mes'] = $result->fetch_assoc()['total'];

                    // Receita total
                    $result = $conn->query("SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos WHERE status = 'Pago'");
                    $stats['receita_total'] = $result->fetch_assoc()['total'];

                    // Pagamentos pendentes
                    $result = $conn->query("SELECT COUNT(*) as total FROM pagamentos WHERE status = 'Pendente'");
                    $stats['pagamentos_pendentes'] = $result->fetch_assoc()['total'];

                    // Notificações não lidas
                    $result = $conn->query("SELECT COUNT(*) as total FROM notificacoes WHERE lida = 0");
                    $stats['notificacoes_nao_lidas'] = $result->fetch_assoc()['total'];

                    // Novos alunos este mês
                    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE (tipo_usuario = 'Aluno' OR tipo_usuario IS NULL) AND MONTH(data_cadastro) = MONTH(NOW()) AND YEAR(data_cadastro) = YEAR(NOW())");
                    $stats['novos_alunos_mes'] = $result->fetch_assoc()['total'];

                    // Assinaturas que vencem em 7 dias
                    $result = $conn->query("SELECT COUNT(*) as total FROM assinaturas WHERE status = 'Ativa' AND data_fim BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)");
                    $stats['assinaturas_vencendo'] = $result->fetch_assoc()['total'];

                    // Distribuição por faixa
                    $result = $conn->query("SELECT faixa, COUNT(*) as total FROM usuarios WHERE (tipo_usuario = 'Aluno' OR tipo_usuario IS NULL) AND status = 'Ativo' GROUP BY faixa");
                    $stats['distribuicao_faixas'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Últimos 7 dias - novos cadastros
                    $result = $conn->query("
                        SELECT DATE(data_cadastro) as data, COUNT(*) as total
                        FROM usuarios 
                        WHERE (tipo_usuario = 'Aluno' OR tipo_usuario IS NULL) 
                        AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY DATE(data_cadastro)
                        ORDER BY data DESC
                    ");
                    $stats['cadastros_7_dias'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Últimos 7 dias - receita
                    $result = $conn->query("
                        SELECT DATE(data_pagamento) as data, SUM(valor) as total
                        FROM pagamentos 
                        WHERE status = 'Pago' 
                        AND data_pagamento >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY DATE(data_pagamento)
                        ORDER BY data DESC
                    ");
                    $stats['receita_7_dias'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Top 5 planos mais vendidos
                    $result = $conn->query("
                        SELECT p.nome, COUNT(a.id) as total_vendas, SUM(pg.valor) as receita_total
                        FROM planos p
                        LEFT JOIN assinaturas a ON p.id = a.plano_id
                        LEFT JOIN pagamentos pg ON a.usuario_id = pg.usuario_id
                        WHERE pg.status = 'Pago'
                        GROUP BY p.id, p.nome
                        ORDER BY total_vendas DESC
                        LIMIT 5
                    ");
                    $stats['top_planos'] = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $stats
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'dashboard_recent_activities':
            if ($method === 'GET') {
                try {
                    $activities = [];

                    // Últimos alunos cadastrados
                    $result = $conn->query("
                        SELECT 'novo_aluno' as tipo, nome, email, data_cadastro as data
                        FROM usuarios 
                        WHERE (tipo_usuario = 'Aluno' OR tipo_usuario IS NULL)
                        ORDER BY data_cadastro DESC 
                        LIMIT 5
                    ");
                    $novos_alunos = $result->fetch_all(MYSQLI_ASSOC);

                    // Últimos pagamentos
                    $result = $conn->query("
                        SELECT 'pagamento' as tipo, u.nome, pg.valor, pg.data_pagamento as data
                        FROM pagamentos pg
                        JOIN usuarios u ON pg.usuario_id = u.id
                        WHERE pg.status = 'Pago'
                        ORDER BY pg.data_pagamento DESC 
                        LIMIT 5
                    ");
                    $pagamentos = $result->fetch_all(MYSQLI_ASSOC);

                    // Últimas notificações
                    $result = $conn->query("
                        SELECT 'notificacao' as tipo, titulo as nome, '' as valor, data_envio as data
                        FROM notificacoes
                        ORDER BY data_envio DESC 
                        LIMIT 5
                    ");
                    $notificacoes = $result->fetch_all(MYSQLI_ASSOC);

                    // Combinar e ordenar todas as atividades
                    $activities = array_merge($novos_alunos, $pagamentos, $notificacoes);
                    
                    // Ordenar por data
                    usort($activities, function($a, $b) {
                        return strtotime($b['data']) - strtotime($a['data']);
                    });

                    // Pegar apenas os 10 mais recentes
                    $activities = array_slice($activities, 0, 10);

                    echo json_encode([
                        'success' => true,
                        'data' => $activities
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'dashboard_alerts':
            if ($method === 'GET') {
                try {
                    $alerts = [];

                    // Assinaturas vencendo em 7 dias
                    $result = $conn->query("
                        SELECT u.nome, u.email, a.data_fim
                        FROM assinaturas a
                        JOIN usuarios u ON a.usuario_id = u.id
                        WHERE a.status = 'Ativa' 
                        AND a.data_fim BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                        ORDER BY a.data_fim ASC
                    ");
                    $assinaturas_vencendo = $result->fetch_all(MYSQLI_ASSOC);

                    foreach ($assinaturas_vencendo as $assinatura) {
                        $alerts[] = [
                            'tipo' => 'warning',
                            'titulo' => 'Assinatura vencendo',
                            'mensagem' => "A assinatura de {$assinatura['nome']} vence em " . date('d/m/Y', strtotime($assinatura['data_fim'])),
                            'data' => $assinatura['data_fim']
                        ];
                    }

                    // Pagamentos pendentes há mais de 3 dias
                    $result = $conn->query("
                        SELECT u.nome, u.email, pg.valor, pg.data_pagamento
                        FROM pagamentos pg
                        JOIN usuarios u ON pg.usuario_id = u.id
                        WHERE pg.status = 'Pendente' 
                        AND pg.data_pagamento < DATE_SUB(NOW(), INTERVAL 3 DAY)
                        ORDER BY pg.data_pagamento ASC
                    ");
                    $pagamentos_pendentes = $result->fetch_all(MYSQLI_ASSOC);

                    foreach ($pagamentos_pendentes as $pagamento) {
                        $alerts[] = [
                            'tipo' => 'danger',
                            'titulo' => 'Pagamento pendente',
                            'mensagem' => "Pagamento de {$pagamento['nome']} (R$ {$pagamento['valor']}) pendente há mais de 3 dias",
                            'data' => $pagamento['data_pagamento']
                        ];
                    }

                    // Muitas notificações não lidas
                    $result = $conn->query("SELECT COUNT(*) as total FROM notificacoes WHERE lida = 0");
                    $nao_lidas = $result->fetch_assoc()['total'];

                    if ($nao_lidas > 10) {
                        $alerts[] = [
                            'tipo' => 'info',
                            'titulo' => 'Muitas notificações não lidas',
                            'mensagem' => "Você tem {$nao_lidas} notificações não lidas",
                            'data' => date('Y-m-d H:i:s')
                        ];
                    }

                    // Ordenar por data
                    usort($alerts, function($a, $b) {
                        return strtotime($a['data']) - strtotime($b['data']);
                    });

                    echo json_encode([
                        'success' => true,
                        'data' => $alerts
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^notificacoes_(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT n.*, u.nome as usuario_nome, u.email as usuario_email
                        FROM notificacoes n
                        LEFT JOIN usuarios u ON n.usuario_id = u.id
                        WHERE n.id = ?
                    ");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $notificacao = $result->fetch_assoc();

                    if (!$notificacao) {
                        throw new Exception('Notificação não encontrada');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $notificacao
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios_add':
            if ($method === 'POST') {
                if (!isset($data['dia_semana']) || !isset($data['hora_inicio']) || !isset($data['hora_fim']) || !isset($data['modalidade'])) {
                    throw new Exception('Dados de cadastro incompletos');
                }

                $dia_semana = $data['dia_semana'];
                $hora_inicio = $data['hora_inicio'];
                $hora_fim = $data['hora_fim'];
                $modalidade = $data['modalidade'];
                $professor_id = isset($data['professor_id']) && !empty($data['professor_id']) ? $data['professor_id'] : null;
                $capacidade_maxima = isset($data['capacidade_maxima']) ? $data['capacidade_maxima'] : 20;
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar conflito de horários
                    $stmt = $conn->prepare("
                        SELECT id FROM horarios 
                        WHERE dia_semana = ? 
                        AND ((hora_inicio <= ? AND hora_fim > ?) OR (hora_inicio < ? AND hora_fim >= ?))
                        AND status = 'Ativo'
                    ");
                    $stmt->bind_param("sssss", $dia_semana, $hora_inicio, $hora_inicio, $hora_fim, $hora_fim);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe um horário conflitante neste período.');
                    }

                    // Inserir novo horário
                    $stmt = $conn->prepare("
                        INSERT INTO horarios (dia_semana, hora_inicio, hora_fim, modalidade, professor_id, capacidade_maxima, descricao, status, data_criacao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("ssssiiiss", $dia_semana, $hora_inicio, $hora_fim, $modalidade, $professor_id, $capacidade_maxima, $descricao, $status);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao inserir horário: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Horário adicionado com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios_update':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['dia_semana']) || !isset($data['hora_inicio']) || !isset($data['hora_fim']) || !isset($data['modalidade'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $dia_semana = $data['dia_semana'];
                $hora_inicio = $data['hora_inicio'];
                $hora_fim = $data['hora_fim'];
                $modalidade = $data['modalidade'];
                $professor_id = isset($data['professor_id']) && !empty($data['professor_id']) ? $data['professor_id'] : null;
                $capacidade_maxima = isset($data['capacidade_maxima']) ? $data['capacidade_maxima'] : 20;
                $descricao = isset($data['descricao']) ? $data['descricao'] : '';
                $status = isset($data['status']) ? $data['status'] : 'Ativo';

                try {
                    // Verificar conflito de horários (excluindo o próprio horário)
                    $stmt = $conn->prepare("
                        SELECT id FROM horarios 
                        WHERE dia_semana = ? 
                        AND ((hora_inicio <= ? AND hora_fim > ?) OR (hora_inicio < ? AND hora_fim >= ?))
                        AND status = 'Ativo'
                        AND id != ?
                    ");
                    $stmt->bind_param("sssssi", $dia_semana, $hora_inicio, $hora_inicio, $hora_fim, $hora_fim, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Já existe um horário conflitante neste período.');
                    }

                    // Atualizar horário
                    $stmt = $conn->prepare("
                        UPDATE horarios 
                        SET dia_semana = ?, hora_inicio = ?, hora_fim = ?, modalidade = ?, professor_id = ?, capacidade_maxima = ?, descricao = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssssiiissi", $dia_semana, $hora_inicio, $hora_fim, $modalidade, $professor_id, $capacidade_maxima, $descricao, $status, $id);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao atualizar horário: ' . $stmt->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Horário atualizado com sucesso!'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios_status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE horarios SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'horarios_delete_dependencies':
            if ($method === 'POST') {
                if (!isset($data['horario_id'])) {
                    throw new Exception('ID do horário não fornecido');
                }

                $horario_id = $data['horario_id'];

                try {
                    // Iniciar transação
                    $conn->begin_transaction();

                    // 1. Excluir registros de frequência relacionados ao horário
                    $stmt = $conn->prepare("DELETE FROM frequencia WHERE horario_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $horario_id);
                        $stmt->execute();
                    }

                    // 2. Excluir aulas relacionadas ao horário
                    $stmt = $conn->prepare("DELETE FROM aulas WHERE horario_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $horario_id);
                        $stmt->execute();
                    }

                    // 3. Excluir reservas relacionadas ao horário
                    $stmt = $conn->prepare("DELETE FROM reservas WHERE horario_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $horario_id);
                        $stmt->execute();
                    }

                    // Confirmar todas as operações
                    $conn->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    // Reverter todas as operações em caso de erro
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            break;

        case 'horarios_delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM horarios WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Horário não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Horário excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios_frequencia':
            if ($method === 'GET') {
                if (!isset($_GET['horario_id'])) {
                    throw new Exception('ID do horário não fornecido');
                }

                $horario_id = $_GET['horario_id'];

                try {
                    $stmt = $conn->prepare("
                        SELECT f.*, u.nome as aluno_nome, u.email as aluno_email, u.faixa as aluno_faixa
                        FROM frequencia f 
                        JOIN usuarios u ON f.usuario_id = u.id 
                        WHERE f.horario_id = ? 
                                                ORDER BY f.data_presenca DESC
                    ");
                    $stmt->bind_param("i", $horario_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $frequencia = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $frequencia,
                        'count' => count($frequencia)
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios_professores_disponiveis':
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT id, nome, faixa, grau, email 
                        FROM professores 
                        WHERE status = 'Ativo' 
                        ORDER BY nome
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $professores = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $professores
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'horarios_estatisticas':
            if ($method === 'GET') {
                try {
                    // Estatísticas gerais dos horários
                    $stats = [];

                    // Total de horários ativos
                    $result = $conn->query("SELECT COUNT(*) as total FROM horarios WHERE status = 'Ativo'");
                    $stats['total_horarios_ativos'] = $result->fetch_assoc()['total'];

                    // Total de horários por modalidade
                    $result = $conn->query("
                        SELECT modalidade, COUNT(*) as total 
                        FROM horarios 
                        WHERE status = 'Ativo' 
                        GROUP BY modalidade
                    ");
                    $stats['por_modalidade'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Total de horários por dia da semana
                    $result = $conn->query("
                        SELECT dia_semana, COUNT(*) as total 
                        FROM horarios 
                        WHERE status = 'Ativo' 
                        GROUP BY dia_semana 
                        ORDER BY FIELD(dia_semana, 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo')
                    ");
                    $stats['por_dia_semana'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Horários com mais frequência (últimos 30 dias)
                    $result = $conn->query("
                        SELECT h.id, h.dia_semana, h.hora_inicio, h.modalidade, COUNT(f.id) as total_presencas
                        FROM horarios h
                        LEFT JOIN frequencia f ON h.id = f.horario_id AND f.data_presenca >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        WHERE h.status = 'Ativo'
                        GROUP BY h.id
                        ORDER BY total_presencas DESC
                        LIMIT 5
                    ");
                    $stats['mais_frequentados'] = $result->fetch_all(MYSQLI_ASSOC);

                    // Professores com mais horários
                    $result = $conn->query("
                        SELECT p.nome, COUNT(h.id) as total_horarios
                        FROM professores p
                        JOIN horarios h ON p.id = h.professor_id
                        WHERE h.status = 'Ativo' AND p.status = 'Ativo'
                        GROUP BY p.id
                        ORDER BY total_horarios DESC
                        LIMIT 5
                    ");
                    $stats['professores_mais_horarios'] = $result->fetch_all(MYSQLI_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $stats
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case (preg_match('/^horarios_(\d+)$/', $endpoint, $matches) ? true : false):
            $id = $matches[1];
            if ($method === 'GET') {
                try {
                    $stmt = $conn->prepare("
                        SELECT h.*, p.nome as professor_nome, p.faixa as professor_faixa
                        FROM horarios h
                        LEFT JOIN professores p ON h.professor_id = p.id
                        WHERE h.id = ?
                    ");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $horario = $result->fetch_assoc();

                    if (!$horario) {
                        throw new Exception('Horário não encontrado');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $horario
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        case 'professores/status':
            if ($method === 'POST') {
                if (!isset($data['id']) || !isset($data['status'])) {
                    throw new Exception('Dados de atualização incompletos');
                }

                $id = $data['id'];
                $status = $data['status'];

                try {
                    $stmt = $conn->prepare("UPDATE professores SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao atualizar status: ' . $e->getMessage()]);
                }
            }
            break;

        case 'professores/delete':
            if ($method === 'POST') {
                if (!isset($data['id'])) {
                    throw new Exception('Dados de exclusão incompletos');
                }

                $id = $data['id'];

                try {
                    $stmt = $conn->prepare("DELETE FROM professores WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception('Erro na preparação da query: ' . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao executar a query: ' . $stmt->error);
                    }

                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Professor não encontrado ou não pode ser excluído');
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Professor excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;


        case 'responder-notificacao':
            if ($method === 'POST') {
                if (!isset($data['notificacao_id']) || !isset($data['resposta'])) {
                    throw new Exception('Dados da resposta incompletos');
                }

                $notificacao_id = $data['notificacao_id'];
                $resposta = $data['resposta'];

                try {
                    // Primeiro, obter informações da notificação original
                    $stmt = $conn->prepare("SELECT usuario_id, titulo FROM notificacoes WHERE id = ?");
                    $stmt->bind_param("i", $notificacao_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $notificacao_original = $result->fetch_assoc();

                    if (!$notificacao_original) {
                        throw new Exception('Notificação original não encontrada');
                    }

                    // Inserir a resposta como uma nova notificação
                    $titulo = "RE: " . $notificacao_original['titulo'];
                    $usuario_id = $notificacao_original['usuario_id'];
                    $tipo = 'admin_para_aluno';

                    $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $usuario_id, $titulo, $resposta, $tipo);

                    if (!$stmt->execute()) {
                        throw new Exception('Erro ao salvar resposta: ' . $stmt->error);
                    }

                    // Marcar a notificação original como lida
                    $stmt = $conn->prepare("UPDATE notificacoes SET lida = TRUE WHERE id = ?");
                    $stmt->bind_param("i", $notificacao_id);
                    $stmt->execute();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Resposta enviada com sucesso!',
                        'id' => $conn->insert_id
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Endpoint não encontrado: ' . $endpoint
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>