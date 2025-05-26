from flask import Flask, request, jsonify, send_from_directory, render_template_string
from flask_cors import CORS
import mysql.connector
from datetime import datetime
import logging
import os
import json

# Configurar logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

app = Flask(__name__, static_folder='.')
# Configurar CORS para permitir requisições do XAMPP
CORS(app, resources={
    r"/api/*": {
        "origins": ["http://localhost", "http://127.0.0.1", "http://localhost:80", "http://localhost/teste"],
        "methods": ["GET", "POST", "PUT", "DELETE"],
        "allow_headers": ["Content-Type"]
    }
})

# Configuração do banco de dados
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'bushido_academy'
}

def get_db_connection():
    """Estabelece conexão com o banco de dados"""
    try:
        conn = mysql.connector.connect(**db_config)
        return conn
    except mysql.connector.Error as err:
        logger.error(f"Erro ao conectar ao banco de dados: {err}")
        raise

# Rotas para servir arquivos estáticos
@app.route('/')
def serve_index():
    return send_from_directory('.', 'index.html')

@app.route('/login')
def serve_login():
    return send_from_directory('.', 'login.html')

@app.route('/dashboard')
def serve_dashboard():
    return send_from_directory('.', 'dashboard.html')

@app.route('/professor-dashboard')
def serve_professor_dashboard():
    return send_from_directory('.', 'professor-dashboard.html')

@app.route('/register')
def serve_register():
    return send_from_directory('.', 'register.html')

@app.route('/planos')
def serve_planos():
    return send_from_directory('.', 'planos.html')

@app.route('/pagamento')
def serve_pagamento():
    return send_from_directory('.', 'pagamento.html')

@app.route('/<path:path>')
def serve_static(path):
    if os.path.exists(path):
        return send_from_directory('.', path)
    return 'File not found', 404

# Rotas da API
@app.route('/api/register', methods=['POST'])
def register():
    try:
        data = request.get_json()
        logger.info(f"Dados recebidos: {data}")
        
        if not all(key in data for key in ['name', 'email', 'password']):
            return jsonify({
                'success': False,
                'message': 'Todos os campos são obrigatórios.'
            }), 400

        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute("SELECT id FROM usuarios WHERE email = %s", (data['email'],))
        if cursor.fetchone():
            return jsonify({
                'success': False,
                'message': 'Este email já está cadastrado.'
            }), 400
        
        cursor.execute("""
            INSERT INTO usuarios (nome, email, senha, faixa, data_cadastro, status)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (
            data['name'],
            data['email'],
            data['password'],
            'Branca',
            datetime.now(),
            'Ativo'
        ))
        
        conn.commit()
        user_id = cursor.lastrowid
        
        return jsonify({
            'success': True,
            'message': 'Cadastro realizado com sucesso!',
            'userId': user_id
        }), 201
        
    except Exception as e:
        logger.error(f"Erro ao registrar usuário: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao realizar cadastro. Por favor, tente novamente.'
        }), 500
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

@app.route('/api/login', methods=['POST'])
def login():
    try:
        data = request.get_json()
        tipo = data.get('tipo', 'aluno')
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        if tipo == 'professor':
            cursor.execute("""
                SELECT id, nome, email, faixa, grau, especialidade
                FROM professores
                WHERE email = %s AND senha = %s
            """, (data['email'], data['password']))
        else:
            cursor.execute("""
                SELECT id, nome, email, faixa
                FROM usuarios
                WHERE email = %s AND senha = %s
            """, (data['email'], data['password']))
        
        user = cursor.fetchone()
        
        if user:
            if tipo == 'professor':
                cursor.execute("UPDATE professores SET ultimo_acesso = NOW() WHERE id = %s", (user['id'],))
            else:
                cursor.execute("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = %s", (user['id'],))
            
            conn.commit()
            
            return jsonify({
                'success': True,
                'message': 'Login realizado com sucesso!',
                'user': user,
                'tipo': tipo
            }), 200
        else:
            return jsonify({
                'success': False,
                'message': 'Email ou senha incorretos.'
            }), 401
            
    except Exception as e:
        logger.error(f"Erro ao realizar login: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao realizar login. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

@app.route('/api/plans', methods=['GET'])
def get_plans():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT *
            FROM planos
            WHERE status = 'Ativo'
            ORDER BY valor
        """)
        
        plans = cursor.fetchall()
        return jsonify(plans), 200
        
    except Exception as e:
        logger.error(f"Erro ao obter planos: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao obter planos. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

@app.route('/api/payment', methods=['POST'])
def process_payment():
    try:
        data = request.get_json()
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Registrar pagamento
        cursor.execute("""
            INSERT INTO pagamentos (usuario_id, valor, data_pagamento, status)
            VALUES (%s, %s, NOW(), 'Pago')
        """, (data['userId'], data['amount']))
        
        # Registrar assinatura
        cursor.execute("""
            INSERT INTO assinaturas (usuario_id, plano_id, data_inicio, data_fim, status)
            VALUES (%s, %s, NOW(), %s, 'Ativa')
        """, (data['userId'], data['planId'], data['endDate']))
        
        conn.commit()
        
        return jsonify({
            'success': True,
            'message': 'Pagamento processado com sucesso!'
        }), 200
        
    except Exception as e:
        logger.error(f"Erro ao processar pagamento: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao processar pagamento. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

@app.route('/api/schedule', methods=['GET'])
def get_schedule():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT h.*, p.nome as professor_nome
            FROM horarios h
            LEFT JOIN professores p ON h.professor_id = p.id
            ORDER BY h.dia_semana, h.horario_inicio
        """)
        
        schedule = cursor.fetchall()
        return jsonify(schedule), 200
        
    except Exception as e:
        logger.error(f"Erro ao obter horários: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao obter horários. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

@app.route('/api/attendance', methods=['POST'])
def register_attendance():
    try:
        data = request.get_json()
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT id FROM frequencia
            WHERE usuario_id = %s AND DATE(data_presenca) = CURDATE()
        """, (data['userId'],))
        
        if cursor.fetchone():
            return jsonify({
                'success': False,
                'message': 'Presença já registrada para hoje.'
            }), 400
        
        cursor.execute("""
            INSERT INTO frequencia (usuario_id, data_presenca, horario_id)
            VALUES (%s, NOW(), %s)
        """, (data['userId'], data['scheduleId']))
        
        cursor.execute("""
            UPDATE horarios
            SET vagas_disponiveis = vagas_disponiveis - 1
            WHERE id = %s
        """, (data['scheduleId'],))
        
        conn.commit()
        
        return jsonify({
            'success': True,
            'message': 'Presença registrada com sucesso!'
        }), 200
        
    except Exception as e:
        logger.error(f"Erro ao registrar presença: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao registrar presença. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

@app.route('/api/user/profile', methods=['GET'])
def get_user_profile():
    try:
        user_id = request.args.get('userId')
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT id, nome, email, faixa, data_cadastro, ultimo_acesso
            FROM usuarios
            WHERE id = %s
        """, (user_id,))
        
        user = cursor.fetchone()
        if user:
            return jsonify({
                'success': True,
                'user': user
            }), 200
        else:
            return jsonify({
                'success': False,
                'message': 'Usuário não encontrado.'
            }), 404
            
    except Exception as e:
        logger.error(f"Erro ao obter perfil do usuário: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao obter perfil. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

@app.route('/api/user/payments', methods=['GET'])
def get_user_payments():
    try:
        user_id = request.args.get('userId')
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT p.*, pl.nome as plano_nome
            FROM pagamentos p
            JOIN assinaturas a ON p.usuario_id = a.usuario_id
            JOIN planos pl ON a.plano_id = pl.id
            WHERE p.usuario_id = %s
            ORDER BY p.data_pagamento DESC
        """, (user_id,))
        
        payments = cursor.fetchall()
        return jsonify({
            'success': True,
            'payments': payments
        }), 200
            
    except Exception as e:
        logger.error(f"Erro ao obter pagamentos do usuário: {e}")
        return jsonify({
            'success': False,
            'message': 'Erro ao obter pagamentos. Por favor, tente novamente.'
        }), 500
    finally:
        cursor.close()
        conn.close()

if __name__ == '__main__':
    # Configurar host e porta para produção
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port) 