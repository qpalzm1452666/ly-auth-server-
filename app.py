from flask import Flask, request, jsonify, render_template
from flask_cors import CORS
import sqlite3
import hashlib
import secrets
import time
from datetime import datetime, timedelta
import os

app = Flask(__name__)
CORS(app)

DB_PATH = os.path.join(os.path.dirname(__file__), 'database.db')

def init_db():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute("""
        CREATE TABLE IF NOT EXISTS keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_text TEXT UNIQUE NOT NULL,
            hwid TEXT DEFAULT NULL,
            used INTEGER DEFAULT 0,
            created_at INTEGER DEFAULT (strftime('%s','now')),
            expires_at INTEGER DEFAULT NULL,
            game_name TEXT DEFAULT 'Ly枪战辅助',
            last_verify INTEGER DEFAULT NULL,
            verify_count INTEGER DEFAULT 0
        )
    """)
    conn.commit()
    conn.close()

init_db()

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/verify', methods=['POST'])
def verify():
    data = request.get_json() or {}
    key_text = data.get('key', '').strip()
    hwid = data.get('hwid', '').strip()

    if not key_text or not hwid:
        return jsonify({"success": False, "message": "缺少参数"}), 400

    conn = get_db()
    c = conn.cursor()
    c.execute("SELECT * FROM keys WHERE key_text = ?", (key_text,))
    row = c.fetchone()

    if not row:
        conn.close()
        return jsonify({"success": False, "message": "卡密不存在"})

    now = int(time.time())

    # 检查是否过期
    if row['expires_at'] and now > row['expires_at']:
        conn.close()
        return jsonify({"success": False, "message": "卡密已过期"})

    # 检查是否已绑定其他设备
    if row['used'] and row['hwid'] and row['hwid'] != hwid:
        conn.close()
        return jsonify({"success": False, "message": "卡密已绑定其他设备"})

    # 首次验证：绑定设备
    if not row['used'] or not row['hwid']:
        c.execute("UPDATE keys SET hwid = ?, used = 1, last_verify = ?, verify_count = verify_count + 1 WHERE id = ?",
                  (hwid, now, row['id']))
        conn.commit()
        conn.close()
        return jsonify({"success": True, "message": "验证成功（首次绑定）", "bind": True})

    # 已绑定当前设备：更新验证时间
    c.execute("UPDATE keys SET last_verify = ?, verify_count = verify_count + 1 WHERE id = ?",
              (now, row['id']))
    conn.commit()
    conn.close()
    return jsonify({"success": True, "message": "验证成功", "bind": False})

@app.route('/api/keys', methods=['GET'])
def list_keys():
    conn = get_db()
    c = conn.cursor()
    c.execute("SELECT * FROM keys ORDER BY created_at DESC")
    rows = c.fetchall()
    conn.close()

    keys = []
    now = int(time.time())
    for r in rows:
        status = "未使用"
        if r['used']:
            if r['expires_at'] and now > r['expires_at']:
                status = "已过期"
            else:
                status = "已使用"

        keys.append({
            "id": r['id'],
            "key": r['key_text'],
            "hwid": r['hwid'] or "-",
            "status": status,
            "created": datetime.fromtimestamp(r['created_at']).strftime('%Y-%m-%d %H:%M'),
            "expires": datetime.fromtimestamp(r['expires_at']).strftime('%Y-%m-%d %H:%M') if r['expires_at'] else "永久",
            "last_verify": datetime.fromtimestamp(r['last_verify']).strftime('%Y-%m-%d %H:%M') if r['last_verify'] else "-",
            "verify_count": r['verify_count']
        })
    return jsonify(keys)

@app.route('/api/add_key', methods=['POST'])
def add_key():
    data = request.get_json() or {}
    key_text = data.get('key', '').strip()
    days = data.get('days', 0)

    if not key_text:
        # 自动生成卡密
        key_text = secrets.token_hex(8).upper()[:16]

    expires = None
    if days and days > 0:
        expires = int(time.time()) + days * 86400

    conn = get_db()
    c = conn.cursor()
    try:
        c.execute("INSERT INTO keys (key_text, expires_at) VALUES (?, ?)", (key_text, expires))
        conn.commit()
        conn.close()
        return jsonify({"success": True, "key": key_text})
    except sqlite3.IntegrityError:
        conn.close()
        return jsonify({"success": False, "message": "卡密已存在"})

@app.route('/api/delete_key', methods=['POST'])
def delete_key():
    data = request.get_json() or {}
    key_id = data.get('id')
    conn = get_db()
    c = conn.cursor()
    c.execute("DELETE FROM keys WHERE id = ?", (key_id,))
    conn.commit()
    conn.close()
    return jsonify({"success": True})

@app.route('/api/batch_keys', methods=['POST'])
def batch_keys():
    data = request.get_json() or {}
    count = min(data.get('count', 1), 100)
    days = data.get('days', 0)
    prefix = data.get('prefix', 'LY')

    expires = None
    if days and days > 0:
        expires = int(time.time()) + days * 86400

    conn = get_db()
    c = conn.cursor()
    generated = []
    for _ in range(count):
        key_text = prefix + "-" + secrets.token_hex(6).upper()[:12]
        try:
            c.execute("INSERT INTO keys (key_text, expires_at) VALUES (?, ?)", (key_text, expires))
            generated.append(key_text)
        except:
            pass
    conn.commit()
    conn.close()
    return jsonify({"success": True, "keys": generated, "count": len(generated)})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
