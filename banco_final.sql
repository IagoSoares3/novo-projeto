-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS bushido_academy;
USE bushido_academy;

select * from usuarios;
select * from cartoes;
select * from assinaturas;
select * from notificacoes;
select * from pagamentos;


-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    faixa ENUM('Branca', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta') DEFAULT 'Branca',
    data_cadastro DATETIME NOT NULL,
    ultimo_acesso DATETIME,
    status ENUM('Ativo', 'Inativo', 'Bloqueado') DEFAULT 'Ativo',
    tipo_usuario ENUM('Aluno', 'Admin') NOT NULL DEFAULT 'Aluno'
);

-- Tabela de cartões de crédito
CREATE TABLE IF NOT EXISTS cartoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    numero VARCHAR(16) NOT NULL,
    nome_titular VARCHAR(100) NOT NULL,
    data_vencimento DATE NOT NULL,
    cvv VARCHAR(4) NOT NULL,
    bandeira VARCHAR(20) NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de planos
CREATE TABLE IF NOT EXISTS planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    duracao_meses INT NOT NULL,
    beneficios TEXT,
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo'
);

-- Tabela de assinaturas
CREATE TABLE IF NOT EXISTS assinaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    plano_id INT NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    status ENUM('Ativa', 'Cancelada', 'Expirada') DEFAULT 'Ativa',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (plano_id) REFERENCES planos(id)
);

-- Tabela de pagamentos
CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_pagamento DATETIME NOT NULL,
    status ENUM('Pago', 'Pendente', 'Cancelado') DEFAULT 'Pendente',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de horários
CREATE TABLE IF NOT EXISTS horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana ENUM('Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado') NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    faixa ENUM('Branca', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta', 'Todas') NOT NULL,
    professor_id INT,
    vagas INT DEFAULT 20,
    vagas_disponiveis INT DEFAULT 20
);

-- Tabela de frequência
CREATE TABLE IF NOT EXISTS frequencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    data_presenca DATETIME NOT NULL,
    horario_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (horario_id) REFERENCES horarios(id)
);

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    mensagem TEXT NOT NULL,
    data_envio DATETIME NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de professores
CREATE TABLE IF NOT EXISTS professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    faixa ENUM('Preta', 'Marrom', 'Roxa') NOT NULL,
    grau INT NOT NULL,
    especialidade VARCHAR(100),
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo'
);

-- Inserção de dados iniciais

-- Planos
INSERT INTO planos (nome, descricao, valor, duracao_meses, beneficios) VALUES
('Plano Mensal', 'Acesso a todas as aulas do mês', 150.00, 1, 'Kimono, Faixa, Aulas ilimitadas'),
('Plano Semestral', 'Acesso a todas as aulas por 6 meses', 800.00, 6, 'Kimono, Faixa, Aulas ilimitadas, Exame de faixa'),
('Plano Anual', 'Acesso a todas as aulas por 12 meses', 1500.00, 12, 'Kimono, Faixa, Aulas ilimitadas, Exame de faixa, Camiseta exclusiva');

-- Horários fixos
INSERT INTO horarios (dia_semana, horario_inicio, horario_fim, faixa, vagas, vagas_disponiveis) VALUES
('Segunda', '18:00', '19:00', 'Branca', 20, 20),
('Segunda', '19:00', '20:00', 'Amarela', 20, 20),
('Segunda', '20:00', '21:00', 'Verde', 20, 20),
('Terça', '18:00', '19:00', 'Laranja', 20, 20),
('Terça', '19:00', '20:00', 'Azul', 20, 20),
('Terça', '20:00', '21:00', 'Roxa', 20, 20),
('Quarta', '18:00', '19:00', 'Branca', 20, 20),
('Quarta', '19:00', '20:00', 'Amarela', 20, 20),
('Quarta', '20:00', '21:00', 'Verde', 20, 20),
('Quinta', '18:00', '19:00', 'Laranja', 20, 20),
('Quinta', '19:00', '20:00', 'Azul', 20, 20),
('Quinta', '20:00', '21:00', 'Roxa', 20, 20),
('Sexta', '18:00', '19:00', 'Branca', 20, 20),
('Sexta', '19:00', '20:00', 'Amarela', 20, 20),
('Sexta', '20:00', '21:00', 'Verde', 20, 20),
('Sábado', '09:00', '10:00', 'Todas', 30, 30),
('Sábado', '10:00', '11:00', 'Todas', 30, 30);

-- Professores
INSERT INTO professores (nome, email, senha, faixa, grau, especialidade) VALUES
('Mestre Silva', 'mestre.silva@bushido.com.br', '123456', 'Preta', 5, 'Jiu-Jitsu'),
('Sensei Santos', 'sensei.santos@bushido.com.br', '123456', 'Preta', 4, 'Karate'),
('Professor Oliveira', 'prof.oliveira@bushido.com.br', '123456', 'Preta', 3, 'Judo');

-- Inserção de dados adicionais

-- Professores adicionais
INSERT INTO professores (nome, email, senha, faixa, grau, especialidade) VALUES
('Mestre Carlos', 'mestre.carlos@bushido.com.br', 'senha123', 'Preta', 6, 'Jiu-Jitsu'),
('Sensei Maria', 'sensei.maria@bushido.com.br', 'senha123', 'Preta', 5, 'Karate'),
('Professor João', 'prof.joao@bushido.com.br', 'senha123', 'Preta', 4, 'Judo'),
('Mestre Ana', 'mestre.ana@bushido.com.br', 'senha123', 'Preta', 5, 'Jiu-Jitsu'),
('Sensei Pedro', 'sensei.pedro@bushido.com.br', 'senha123', 'Preta', 4, 'Karate'),
('Professor Lucas', 'prof.lucas@bushido.com.br', 'senha123', 'Preta', 3, 'Judo'),
('Mestre Paula', 'mestre.paula@bushido.com.br', 'senha123', 'Preta', 5, 'Jiu-Jitsu'),
('Sensei Rafael', 'sensei.rafael@bushido.com.br', 'senha123', 'Preta', 4, 'Karate'),
('Professor Beatriz', 'prof.beatriz@bushido.com.br', 'senha123', 'Preta', 3, 'Judo'),
('Mestre Gabriel', 'mestre.gabriel@bushido.com.br', 'senha123', 'Preta', 5, 'Jiu-Jitsu');

-- Usuários (Alunos)
INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, status) VALUES
('João Silva', 'joao.silva@email.com', 'senha123', 'Branca', '2024-01-01 10:00:00', 'Ativo'),
('Maria Santos', 'maria.santos@email.com', 'senha123', 'Amarela', '2024-01-02 11:00:00', 'Ativo'),
('Pedro Oliveira', 'pedro.oliveira@email.com', 'senha123', 'Verde', '2024-01-03 12:00:00', 'Ativo'),
('Ana Costa', 'ana.costa@email.com', 'senha123', 'Azul', '2024-01-04 13:00:00', 'Ativo'),
('Lucas Pereira', 'lucas.pereira@email.com', 'senha123', 'Roxa', '2024-01-05 14:00:00', 'Ativo'),
('Julia Lima', 'julia.lima@email.com', 'senha123', 'Marrom', '2024-01-06 15:00:00', 'Ativo'),
('Rafael Souza', 'rafael.souza@email.com', 'senha123', 'Preta', '2024-01-07 16:00:00', 'Ativo'),
('Beatriz Ferreira', 'beatriz.ferreira@email.com', 'senha123', 'Branca', '2024-01-08 17:00:00', 'Ativo'),
('Gabriel Martins', 'gabriel.martins@email.com', 'senha123', 'Amarela', '2024-01-09 18:00:00', 'Ativo'),
('Isabela Alves', 'isabela.alves@email.com', 'senha123', 'Verde', '2024-01-10 19:00:00', 'Ativo');

INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, tipo_usuario) VALUES 
('João Silva', 'joao@email.com', 'senha123', 'Branca', NOW(), 'Aluno'),
('Maria Santos', 'maria@email.com', 'senha123', 'Azul', NOW(), 'Aluno'),
('Pedro Oliveira', 'pedro@email.com', 'senha123', 'Roxa', NOW(), 'Aluno');

-- Cartões
INSERT INTO cartoes (usuario_id, numero, nome_titular, data_vencimento, cvv, bandeira) VALUES
(1, '4111111111111111', 'João Silva', '2025-12-31', '123', 'Visa'),
(2, '5111111111111111', 'Maria Santos', '2025-11-30', '456', 'Mastercard'),
(3, '341111111111111', 'Pedro Oliveira', '2025-10-31', '789', 'American Express'),
(4, '4111111111111112', 'Ana Costa', '2025-09-30', '321', 'Visa'),
(5, '5111111111111112', 'Lucas Pereira', '2025-08-31', '654', 'Mastercard'),
(6, '341111111111112', 'Julia Lima', '2025-07-31', '987', 'American Express'),
(7, '4111111111111113', 'Rafael Souza', '2025-06-30', '147', 'Visa'),
(8, '5111111111111113', 'Beatriz Ferreira', '2025-05-31', '258', 'Mastercard'),
(9, '341111111111113', 'Gabriel Martins', '2025-04-30', '369', 'American Express'),
(10, '4111111111111114', 'Isabela Alves', '2025-03-31', '741', 'Visa');

-- Assinaturas
INSERT INTO assinaturas (usuario_id, plano_id, data_inicio, data_fim, status) VALUES
(1, 1, '2024-01-01 00:00:00', '2024-02-01 00:00:00', 'Ativa'),
(2, 2, '2024-01-02 00:00:00', '2024-07-02 00:00:00', 'Ativa'),
(3, 3, '2024-01-03 00:00:00', '2025-01-03 00:00:00', 'Ativa'),
(4, 1, '2024-01-04 00:00:00', '2024-02-04 00:00:00', 'Ativa'),
(5, 2, '2024-01-05 00:00:00', '2024-07-05 00:00:00', 'Ativa'),
(6, 3, '2024-01-06 00:00:00', '2025-01-06 00:00:00', 'Ativa'),
(7, 1, '2024-01-07 00:00:00', '2024-02-07 00:00:00', 'Ativa'),
(8, 2, '2024-01-08 00:00:00', '2024-07-08 00:00:00', 'Ativa'),
(9, 3, '2024-01-09 00:00:00', '2025-01-09 00:00:00', 'Ativa'),
(10, 1, '2024-01-10 00:00:00', '2024-02-10 00:00:00', 'Ativa');

-- Pagamentos
INSERT INTO pagamentos (usuario_id, valor, data_pagamento, status) VALUES
(1, 150.00, '2024-01-01 10:00:00', 'Pago'),
(2, 800.00, '2024-01-02 11:00:00', 'Pago'),
(3, 1500.00, '2024-01-03 12:00:00', 'Pago'),
(4, 150.00, '2024-01-04 13:00:00', 'Pago'),
(5, 800.00, '2024-01-05 14:00:00', 'Pago'),
(6, 1500.00, '2024-01-06 15:00:00', 'Pago'),
(7, 150.00, '2024-01-07 16:00:00', 'Pago'),
(8, 800.00, '2024-01-08 17:00:00', 'Pago'),
(9, 1500.00, '2024-01-09 18:00:00', 'Pago'),
(10, 150.00, '2024-01-10 19:00:00', 'Pago');

-- Frequência
INSERT INTO frequencia (usuario_id, data_presenca, horario_id) VALUES
(1, '2024-01-01 18:00:00', 1),
(2, '2024-01-02 19:00:00', 2),
(3, '2024-01-03 20:00:00', 3),
(4, '2024-01-04 18:00:00', 4),
(5, '2024-01-05 19:00:00', 5),
(6, '2024-01-06 20:00:00', 6),
(7, '2024-01-07 18:00:00', 7),
(8, '2024-01-08 19:00:00', 8),
(9, '2024-01-09 20:00:00', 9),
(10, '2024-01-10 18:00:00', 10);

-- Notificações
INSERT INTO notificacoes (usuario_id, titulo, mensagem, data_envio, lida) VALUES
(1, 'Bem-vindo!', 'Seja bem-vindo à Bushido Academy!', '2024-01-01 10:00:00', true),
(2, 'Aula Confirmada', 'Sua aula de hoje foi confirmada.', '2024-01-02 11:00:00', true),
(3, 'Pagamento Recebido', 'Seu pagamento foi processado com sucesso.', '2024-01-03 12:00:00', false),
(4, 'Exame de Faixa', 'Você está elegível para o próximo exame de faixa.', '2024-01-04 13:00:00', false),
(5, 'Renovação de Plano', 'Seu plano está próximo do vencimento.', '2024-01-05 14:00:00', false),
(6, 'Aula Cancelada', 'A aula de hoje foi cancelada.', '2024-01-06 15:00:00', true),
(7, 'Promoção Especial', 'Confira nossas promoções especiais!', '2024-01-07 16:00:00', false),
(8, 'Evento Especial', 'Participe do nosso torneio interno!', '2024-01-08 17:00:00', false),
(9, 'Atualização de Horário', 'Novos horários disponíveis.', '2024-01-09 18:00:00', true),
(10, 'Feedback', 'Como foi sua experiência hoje?', '2024-01-10 19:00:00', false);

-- Inserir usuário admin
INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, status, tipo_usuario) VALUES
('Administrador', 'admin123@gmail.com', 'admin', 'Preta', '2024-01-01 00:00:00', 'Ativo', 'Admin');

-- Índices para melhor performance
CREATE INDEX idx_usuario_email ON usuarios(email);
CREATE INDEX idx_pagamento_status ON pagamentos(status);
CREATE INDEX idx_frequencia_data ON frequencia(data_presenca);
CREATE INDEX idx_notificacao_usuario ON notificacoes(usuario_id, lida);
CREATE INDEX idx_horario_dia ON horarios(dia_semana); 