import mysql.connector
from mysql.connector import Error
import hashlib
from datetime import datetime, timedelta
import json

# Configurações do banco de dados
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'bushido_academy'
}

def get_db_connection():
    """Estabelece conexão com o banco de dados"""
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as err:
        print(f"Erro ao conectar ao banco de dados: {err}")
        return None

def excluir_usuario(usuario_id):
    """Exclui um usuário e todos os seus dados relacionados"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Iniciar transação
        conn.start_transaction()
        
        # Excluir em ordem para respeitar as foreign keys
        # 1. Notificações
        cursor.execute("DELETE FROM notificacoes WHERE usuario_id = %s", (usuario_id,))
        
        # 2. Frequência
        cursor.execute("DELETE FROM frequencia WHERE usuario_id = %s", (usuario_id,))
        
        # 3. Pagamentos
        cursor.execute("DELETE FROM pagamentos WHERE usuario_id = %s", (usuario_id,))
        
        # 4. Assinaturas
        cursor.execute("DELETE FROM assinaturas WHERE usuario_id = %s", (usuario_id,))
        
        # 5. Finalmente, o usuário
        cursor.execute("DELETE FROM usuarios WHERE id = %s", (usuario_id,))
        
        # Verificar se o usuário foi excluído
        if cursor.rowcount == 0:
            conn.rollback()
            return {
                'success': False,
                'message': 'Usuário não encontrado.'
            }
        
        # Confirmar transação
        conn.commit()
        
        return {
            'success': True,
            'message': 'Aluno excluído com sucesso!'
        }
        
    except Exception as e:
        # Reverter transação em caso de erro
        if 'conn' in locals():
            conn.rollback()
        print(f"Erro ao excluir usuário: {str(e)}")
        return {
            'success': False,
            'message': f'Erro ao excluir aluno: {str(e)}'
        }
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

def excluir_professor(professor_id):
    """Exclui um professor e todos os seus dados relacionados"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Iniciar transação
        conn.start_transaction()
        
        # 1. Atualizar horários para remover a referência ao professor
        cursor.execute("UPDATE horarios SET professor_id = NULL WHERE professor_id = %s", (professor_id,))
        
        # 2. Excluir o professor
        cursor.execute("DELETE FROM professores WHERE id = %s", (professor_id,))
        
        # Verificar se o professor foi excluído
        if cursor.rowcount == 0:
            conn.rollback()
            return {
                'success': False,
                'message': 'Professor não encontrado.'
            }
        
        # Confirmar transação
        conn.commit()
        
        return {
            'success': True,
            'message': 'Professor excluído com sucesso!'
        }
        
    except Exception as e:
        # Reverter transação em caso de erro
        if 'conn' in locals():
            conn.rollback()
        print(f"Erro ao excluir professor: {str(e)}")
        return {
            'success': False,
            'message': f'Erro ao excluir professor: {str(e)}'
        }
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

def obter_usuarios():
    """Retorna todos os usuários/alunos"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT u.*, 
                   COUNT(DISTINCT f.id) as total_presencas,
                   COUNT(DISTINCT p.id) as total_pagamentos,
                   MAX(a.data_fim) as assinatura_valida_ate
            FROM usuarios u
            LEFT JOIN frequencia f ON u.id = f.usuario_id
            LEFT JOIN pagamentos p ON u.id = p.usuario_id
            LEFT JOIN assinaturas a ON u.id = a.usuario_id AND a.status = 'Ativa'
            GROUP BY u.id
            ORDER BY u.nome
        """)
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter usuários: {str(e)}")
        return []
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

def obter_professores():
    """Retorna todos os professores"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT p.*,
                   COUNT(DISTINCT h.id) as total_horarios
            FROM professores p
            LEFT JOIN horarios h ON p.id = h.professor_id
            GROUP BY p.id
            ORDER BY p.nome
        """)
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter professores: {str(e)}")
        return []
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()


def inserir_usuario(nome, email, senha, faixa='Branca'):
    """Insere um novo aluno no sistema"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Verificar se o email já existe
        cursor.execute("SELECT id FROM usuarios WHERE email = %s", (email,))
        if cursor.fetchone():
            return {
                'success': False,
                'message': 'Este email já está cadastrado.'
            }
        
        # Inserir novo usuário
        cursor.execute("""
            INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, status)
            VALUES (%s, %s, %s, %s, NOW(), 'Ativo')
        """, (nome, email, senha, faixa))
        
        conn.commit()
        
        return {
            'success': True,
            'message': 'Aluno cadastrado com sucesso na ART OF FIGHT!'
        }
            
    except Exception as e:
        print(f"Erro ao inserir usuário: {str(e)}")
        return {
            'success': False,
            'message': 'Erro ao realizar cadastro. Tente novamente.'
        }
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

def verificar_login(email, senha, tipo='aluno'):
    """Verifica as credenciais do aluno"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        if tipo == 'professor':
            cursor.execute("SELECT * FROM professores WHERE email = %s", (email,))
        else:
            cursor.execute("SELECT * FROM usuarios WHERE email = %s", (email,))
            
        usuario = cursor.fetchone()
        
        if usuario and usuario['senha'] == senha:  # Em produção, use hash de senha
            if tipo == 'professor':
                return {
                    'success': True,
                    'message': 'Login realizado com sucesso!',
                    'user': {
                        'id': usuario['id'],
                        'nome': usuario['nome'],
                        'email': usuario['email'],
                        'faixa': usuario['faixa'],
                        'grau': usuario['grau'],
                        'tipo': 'professor'
                    }
                }
            else:
                return {
                    'success': True,
                    'message': 'Login realizado com sucesso!',
                    'user': {
                        'id': usuario['id'],
                        'nome': usuario['nome'],
                        'email': usuario['email'],
                        'faixa': usuario['faixa'],
                        'tipo': 'aluno'
                    }
                }
        else:
            return {
                'success': False,
                'message': 'Email ou senha incorretos.'
            }
            
    except Exception as e:
        print(f"Erro ao verificar login: {str(e)}")
        return {
            'success': False,
            'message': 'Erro ao realizar login. Tente novamente.'
        }
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

def obter_planos():
    """Retorna todos os planos disponíveis"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT *
            FROM planos
            WHERE status = 'Ativo'
            ORDER BY valor
        """)
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter planos: {str(e)}")
        return []
    finally:
        cursor.close()
        conn.close()

def registrar_pagamento(usuario_id, valor, plano_id):
    """Registra um novo pagamento de mensalidade"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Registrar pagamento
        cursor.execute("""
            INSERT INTO pagamentos (usuario_id, valor, data_pagamento, status)
            VALUES (%s, %s, %s, %s)
        """, (usuario_id, valor, datetime.now(), 'Pago'))
        
        # Calcular data de fim da assinatura
        data_inicio = datetime.now()
        cursor.execute("SELECT duracao_meses FROM planos WHERE id = %s", (plano_id,))
        duracao = cursor.fetchone()[0]
        data_fim = data_inicio + timedelta(days=30*duracao)
        
        # Registrar assinatura
        cursor.execute("""
            INSERT INTO assinaturas (usuario_id, plano_id, data_inicio, data_fim, status)
            VALUES (%s, %s, %s, %s, %s)
        """, (usuario_id, plano_id, data_inicio, data_fim, 'Ativa'))
        
        conn.commit()
        return True, "Pagamento registrado com sucesso!"
        
    except Exception as e:
        return False, f"Erro ao registrar pagamento: {str(e)}"
    finally:
        cursor.close()
        conn.close()

def obter_horarios():
    """Retorna todos os horários disponíveis"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT h.*, p.nome as professor_nome
            FROM horarios h
            LEFT JOIN professores p ON h.professor_id = p.id
            ORDER BY h.dia_semana, h.horario_inicio
        """)
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter horários: {str(e)}")
        return []
    finally:
        cursor.close()
        conn.close()

def registrar_frequencia(usuario_id, horario_id):
    """Registra a presença do aluno no dojo"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Verificar se já existe registro para hoje
        cursor.execute("""
            SELECT id FROM frequencia
            WHERE usuario_id = %s AND DATE(data_presenca) = CURDATE()
        """, (usuario_id,))
        
        if cursor.fetchone():
            return False, "Presença já registrada para hoje"
        
        # Registrar presença
        cursor.execute("""
            INSERT INTO frequencia (usuario_id, data_presenca, horario_id)
            VALUES (%s, %s, %s)
        """, (usuario_id, datetime.now(), horario_id))
        
        # Atualizar vagas disponíveis
        cursor.execute("""
            UPDATE horarios
            SET vagas_disponiveis = vagas_disponiveis - 1
            WHERE id = %s
        """, (horario_id,))
        
        conn.commit()
        return True, "Presença registrada com sucesso!"
        
    except Exception as e:
        return False, f"Erro ao registrar presença: {str(e)}"
    finally:
        cursor.close()
        conn.close()

def obter_frequencia(usuario_id):
    """Retorna o histórico de frequência do aluno"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT f.*, h.dia_semana, h.horario_inicio, h.horario_fim
            FROM frequencia f
            JOIN horarios h ON f.horario_id = h.id
            WHERE f.usuario_id = %s
            ORDER BY f.data_presenca DESC
        """, (usuario_id,))
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter frequência: {str(e)}")
        return []
    finally:
        cursor.close()
        conn.close()

def obter_pagamentos(usuario_id):
    """Retorna o histórico de pagamentos do aluno"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT p.*, a.plano_id, pl.nome as plano_nome
            FROM pagamentos p
            JOIN assinaturas a ON p.usuario_id = a.usuario_id
            JOIN planos pl ON a.plano_id = pl.id
            WHERE p.usuario_id = %s
            ORDER BY p.data_pagamento DESC
        """, (usuario_id,))
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter pagamentos: {str(e)}")
        return []
    finally:
        cursor.close()
        conn.close()

def obter_notificacoes(usuario_id):
    """Retorna as notificações do aluno"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT *
            FROM notificacoes
            WHERE usuario_id = %s
            ORDER BY data_envio DESC
        """, (usuario_id,))
        
        return cursor.fetchall()
        
    except Exception as e:
        print(f"Erro ao obter notificações: {str(e)}")
        return []
    finally:
        cursor.close()
        conn.close()

def marcar_notificacao_lida(notificacao_id):
    """Marca uma notificação como lida"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            UPDATE notificacoes
            SET lida = TRUE
            WHERE id = %s
        """, (notificacao_id,))
        
        conn.commit()
        return True
        
    except Exception as e:
        print(f"Erro ao marcar notificação como lida: {str(e)}")
        return False
    finally:
        cursor.close()
        conn.close()

def inserir_professor(nome, email, senha, faixa, grau, especialidade):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Verificar se o email já existe
        cursor.execute("SELECT id FROM professores WHERE email = %s", (email,))
        if cursor.fetchone():
            return {
                'success': False,
                'message': 'Este email já está cadastrado.'
            }
        
        # Inserir novo professor
        cursor.execute("""
            INSERT INTO professores (nome, email, senha, faixa, grau, especialidade, status)
            VALUES (%s, %s, %s, %s, %s, %s, 'Ativo')
        """, (nome, email, senha, faixa, grau, especialidade))
        
        conn.commit()
        
        return {
            'success': True,
            'message': 'Professor cadastrado com sucesso na ART OF FIGHT!'
        }
            
    except Exception as e:
        print(f"Erro ao inserir professor: {str(e)}")
        return {
            'success': False,
            'message': 'Erro ao realizar cadastro. Tente novamente.'
        }
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

# Exemplo de uso:
if __name__ == "__main__":
    # Teste de conexão
    connection = get_db_connection()
    if connection:
        print("Conexão estabelecida com sucesso!")
        connection.close() 