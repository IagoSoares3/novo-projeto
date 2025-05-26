import http.server
import socketserver
import json
import mysql.connector
from datetime import datetime
import urllib.parse

# Configuração do banco de dados
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'bushido_academy'
}

class RequestHandler(http.server.SimpleHTTPRequestHandler):
    def _send_cors_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')

    def do_OPTIONS(self):
        self.send_response(200)
        self._send_cors_headers()
        self.end_headers()

    def do_POST(self):
        content_length = int(self.headers['Content-Length'])
        post_data = self.rfile.read(content_length)
        data = json.loads(post_data.decode('utf-8'))

        response_data = {}
        status_code = 200

        try:
            if self.path == '/api/register':
                response_data, status_code = self.handle_register(data)
            elif self.path == '/api/login':
                response_data, status_code = self.handle_login(data)
            else:
                response_data = {'error': 'Endpoint não encontrado'}
                status_code = 404

        except Exception as e:
            response_data = {'error': str(e)}
            status_code = 500

        self.send_response(status_code)
        self._send_cors_headers()
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        
        response = json.dumps(response_data).encode('utf-8')
        self.wfile.write(response)

    def handle_register(self, data):
        if not all(key in data for key in ['name', 'email', 'password']):
            return {'error': 'Todos os campos são obrigatórios'}, 400

        try:
            conn = mysql.connector.connect(**db_config)
            cursor = conn.cursor()

            # Verificar se o email já existe
            cursor.execute("SELECT id FROM usuarios WHERE email = %s", (data['email'],))
            if cursor.fetchone():
                return {'error': 'Este email já está cadastrado'}, 400

            # Inserir novo usuário
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

            return {
                'success': True,
                'message': 'Cadastro realizado com sucesso!',
                'userId': user_id
            }, 201

        finally:
            cursor.close()
            conn.close()

    def handle_login(self, data):
        if not all(key in data for key in ['email', 'password']):
            return {'error': 'Email e senha são obrigatórios'}, 400

        try:
            conn = mysql.connector.connect(**db_config)
            cursor = conn.cursor(dictionary=True)

            cursor.execute("""
                SELECT id, nome, email, faixa
                FROM usuarios
                WHERE email = %s AND senha = %s
            """, (data['email'], data['password']))

            user = cursor.fetchone()
            if user:
                return {
                    'success': True,
                    'message': 'Login realizado com sucesso!',
                    'user': user
                }, 200
            else:
                return {
                    'success': False,
                    'message': 'Email ou senha incorretos'
                }, 401

        finally:
            cursor.close()
            conn.close()

if __name__ == '__main__':
    PORT = 5000
    with socketserver.TCPServer(("", PORT), RequestHandler) as httpd:
        print(f"Servidor rodando na porta {PORT}")
        httpd.serve_forever() 