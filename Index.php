from flask import Flask, render_template, request, send_file, redirect, url_for, session
import sqlite3, os
from fpdf import FPDF
from datetime import datetime

app = Flask(__name__)
app.secret_key = 'supersecretkey123'
DB_NAME = 'eurostart.db'

def get_db_connection():
    conn = sqlite3.connect(DB_NAME)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    if os.path.exists(DB_NAME): return
    conn = get_db_connection()
    c = conn.cursor()
    c.execute('CREATE TABLE agencje(id INTEGER PRIMARY KEY, kraj TEXT, nazwa TEXT)')
    c.execute('CREATE TABLE busy(id INTEGER PRIMARY KEY, firma TEXT, trasa TEXT)')
    c.execute('CREATE TABLE opinie_agencji(id INTEGER PRIMARY KEY, agencja_id INTEGER, autor TEXT, tresc TEXT, gwiazdki INTEGER)')
    c.execute('CREATE TABLE opinie_bus(id INTEGER PRIMARY KEY, bus_id INTEGER, autor TEXT, tresc TEXT, gwiazdki INTEGER)')
    c.execute('CREATE TABLE statystyki(id INTEGER PRIMARY KEY, data TEXT, podstrona TEXT, wejscia INTEGER)')
    agencje_start = [
        ('Dania','Agencja Pracy Nord'),
        ('Niemcy','Agencja Arbeit'),
        ('Holandia','Agencja Tulip'),
        ('Francja','Agencja Bleu'),
        ('Belgia','Agencja Brux'),
        ('Norwegia','Agencja Fjord'),
        ('Austria','Agencja Alpen'),
        ('Szwajcaria','Agencja Helvetia'),
        ('Islandia','Agencja Ísland'),
        ('Hiszpania','Agencja Sol'),
        ('Włochy','Agencja Roma')
    ]
    busy_start = [
        ('Bus1','Warszawa → Kopenhaga'),
        ('Bus2','Poznań → Berlin'),
        ('Bus3','Gdańsk → Amsterdam'),
        ('Bus4','Wrocław → Paryż'),
        ('Bus5','Kraków → Bruksela')
    ]
    c.executemany('INSERT INTO agencje(kraj,nazwa) VALUES (?,?)', agencje_start)
    c.executemany('INSERT INTO busy(firma,trasa) VALUES (?,?)', busy_start)
    conn.commit()
    conn.close()

def filtr_wulgaryzmow(text):
    wulgaryzmy = ['kurwa','chuj','pierdole']
    for w in wulgaryzmy:
        text = text.replace(w,'***')
    return text

def dodaj_wejscie(podstrona):
    conn = get_db_connection()
    today = datetime.now().strftime('%Y-%m-%d')
    rekord = conn.execute('SELECT * FROM statystyki WHERE data=? AND podstrona=?',(today,podstrona)).fetchone()
    if rekord:
        conn.execute('UPDATE statystyki SET wejscia=? WHERE id=?',(rekord['wejscia']+1, rekord['id']))
    else:
        conn.execute('INSERT INTO statystyki(data,podstrona,wejscia) VALUES (?,?,?)',(today,podstrona,1))
    conn.commit()
    conn.close()

ADMIN_USER = 'admin'
ADMIN_PASS = 'haslo123'

@app.route('/admin', methods=['GET','POST'])
def admin():
    if 'admin_logged' in session:
        return redirect(url_for('admin_panel'))
    error = ''
    if request.method=='POST':
        if request.form['username']==ADMIN_USER and request.form['password']==ADMIN_PASS:
            session['admin_logged']=True
            return redirect(url_for('admin_panel'))
        else:
            error='Błędne dane'
    return render_template('admin_login.html', error=error)

@app.route('/admin/panel')
def admin_panel():
    if 'admin_logged' not in session:
        return redirect(url_for('admin'))
    conn = get_db_connection()
    opinie_ag = conn.execute('SELECT o.id, a.nazwa, o.autor, o.tresc, o.gwiazdki FROM opinie_agencji o JOIN agencje a ON o.agencja_id=a.id ORDER BY o.id DESC').fetchall()
    opinie_b = conn.execute('SELECT o.id, b.firma, o.autor, o.tresc, o.gwiazdki FROM opinie_bus o JOIN busy b ON o.bus_id=b.id ORDER BY o.id DESC').fetchall()
    busy = conn.execute('SELECT * FROM busy').fetchall()
    agencje = conn.execute('SELECT * FROM agencje').fetchall()
    staty = conn.execute('SELECT * FROM statystyki ORDER BY data DESC').fetchall()
    conn.close()
    return render_template('admin_panel.html', opinie_ag=opinie_ag, opinie_b=opinie_b, busy=busy, agencje=agencje, staty=staty)

@app.route('/admin/logout')
def admin_logout():
    session.pop('admin_logged', None)
    return redirect(url_for('admin'))

@app.route('/')
def index():
    dodaj_wejscie('index')
    conn = get_db_connection()
    agencje = conn.execute('SELECT * FROM agencje').fetchall()
    busy = conn.execute('SELECT * FROM busy').fetchall()
    conn.close()
    return render_template('index.html', agencje=agencje, busy=busy)

@app.route('/opinie_agencji', methods=['GET','POST'])
def opinie_agencji():
    dodaj_wejscie('opinie_agencji')
    conn = get_db_connection()
    agencje = conn.execute('SELECT * FROM agencje').fetchall()
    if request.method=='POST':
        ag_id = int(request.form['agencja_id'])
        autor = request.form['autor']
        tresc = filtr_wulgaryzmow(request.form['tresc'])
        gw = int(request.form['gwiazdki'])
        conn.execute('INSERT INTO opinie_agencji(agencja_id,autor,tresc,gwiazdki) VALUES (?,?,?,?)',(ag_id,autor,tresc,gw))
        conn.commit()
    opinie = conn.execute('''SELECT o.id, o.agencja_id, a.nazwa, o.autor, o.tresc, o.gwiazdki
                             FROM opinie_agencji o JOIN agencje a ON o.agencja_id=a.id
                             ORDER BY o.id DESC''').fetchall()
    conn.close()
    return render_template('opinie_agencji.html', agencje=agencje, opinie=opinie)

@app.route('/opinie_bus', methods=['GET','POST'])
def opinie_bus():
    dodaj_wejscie('opinie_bus')
    conn = get_db_connection()
    busy = conn.execute('SELECT * FROM busy').fetchall()
    if request.method=='POST':
        bus_id = int(request.form['bus_id'])
        autor = request.form['autor']
        tresc = filtr_wulgaryzmow(request.form['tresc'])
        gw = int(request.form['gwiazdki'])
        conn.execute('INSERT INTO opinie_bus(bus_id,autor,tresc,gwiazdki) VALUES (?,?,?,?)',(bus_id,autor,tresc,gw))
        conn.commit()
    opinie = conn.execute('''SELECT o.id, o.bus_id, b.firma, o.autor, o.tresc, o.gwiazdki
                             FROM opinie_bus o JOIN busy b ON o.bus_id=b.id
                             ORDER BY o.id DESC''').fetchall()
    conn.close()
    return render_template('opinie_bus.html', busy=busy, opinie=opinie)

@app.route('/kalkulator', methods=['GET','POST'])
def kalkulator():
    dodaj_wejscie('kalkulator')
    wynik = None
    if request.method=='POST':
        stawka = float(request.form['stawka'])
        godziny = float(request.form['godziny'])
        wynik = round(stawka*godziny*4,2)
    return render_template('kalkulator.html', wynik=wynik)

@app.route('/cv', methods=['GET','POST'])
def cv():
    dodaj_wejscie('cv')
    if request.method=='POST':
        imie = request.form['imie']
        nazwisko = request.form['nazwisko']
        email = request.form['email']
        pdf = FPDF()
        pdf.add_page()
        pdf.set_font("Arial","B",16)
        pdf.cell(0,10,f"CV: {imie} {nazwisko}", ln=True)
        pdf.set_font("Arial","",12)
        pdf.cell(0,10,f"Email: {email}", ln=True)
        pdf_file = f"{imie}_{nazwisko}.pdf"
        pdf.output(pdf_file)
        return send_file(pdf_file, as_attachment=True)
    return render_template('cv.html')

if __name__=='__main__':
    init_db()
    app.run(debug=True)
