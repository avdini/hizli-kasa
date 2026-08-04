import os
import json
import base64
import sys
import threading
import subprocess
from io import BytesIO
from http.server import HTTPServer, BaseHTTPRequestHandler

# Import windows specific printing and UI libraries
try:
    import win32print
    import win32ui
    import win32con
    import winreg
    from PIL import Image, ImageDraw, ImageWin
    import pystray
except ImportError:
    print("Warning: Required desktop libraries not found. Running in mock/development mode.")
    win32print = None
    winreg = None
    pystray = None

# Config location: %APPDATA%/HizliKasa/config.json
APPDATA_DIR = os.path.join(os.environ.get('APPDATA', ''), 'HizliKasa')
CONFIG_PATH = os.path.join(APPDATA_DIR, 'config.json')

# Windows Startup Registry Configuration
REG_KEY = r"Software\Microsoft\Windows\CurrentVersion\Run"
REG_NAME = "HizliKasaPrintHelper"

def is_startup_enabled():
    if winreg is None:
        return False
    try:
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, REG_KEY, 0, winreg.KEY_READ)
        value, _ = winreg.QueryValueEx(key, REG_NAME)
        winreg.CloseKey(key)
        return True
    except FileNotFoundError:
        return False
    except Exception:
        return False

def ensure_startup_registered():
    """Ensure app is automatically listed in Windows Settings -> Apps -> Startup."""
    if winreg is None:
        return
    try:
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, REG_KEY, 0, winreg.KEY_WRITE)
        exe_path = os.path.abspath(sys.argv[0])
        winreg.SetValueEx(key, REG_NAME, 0, winreg.REG_SZ, f'"{exe_path}"')
        winreg.CloseKey(key)
    except Exception as e:
        print(f"Startup registration error: {e}")

def toggle_startup(icon, item):
    if winreg is None:
        return
    enabled = not item.checked
    try:
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, REG_KEY, 0, winreg.KEY_WRITE)
        if enabled:
            exe_path = os.path.abspath(sys.argv[0])
            winreg.SetValueEx(key, REG_NAME, 0, winreg.REG_SZ, f'"{exe_path}"')
            print("Registered to Windows Startup")
        else:
            try:
                winreg.DeleteValue(key, REG_NAME)
                print("Removed from Windows Startup")
            except FileNotFoundError:
                pass
        winreg.CloseKey(key)
    except Exception as e:
        print(f"Error modifying startup registry: {e}")
        
    icon.update_menu()

def load_config():
    if not os.path.exists(CONFIG_PATH):
        return {}
    try:
        with open(CONFIG_PATH, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception:
        return {}

def save_config(config):
    if not os.path.exists(APPDATA_DIR):
        os.makedirs(APPDATA_DIR, exist_ok=True)
    try:
        with open(CONFIG_PATH, 'w', encoding='utf-8') as f:
            json.dump(config, f, ensure_ascii=False, indent=4)
        return True
    except Exception as e:
        print(f"Error saving config: {e}")
        return False

# Load config on startup
config = load_config()

class PrintHelperHandler(BaseHTTPRequestHandler):
    
    def log_message(self, format, *args):
        pass

    def send_cors_headers(self, origin=None):
        self.send_header('Access-Control-Allow-Credentials', 'true')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        if origin:
            self.send_header('Access-Control-Allow-Origin', origin)
        else:
            self.send_header('Access-Control-Allow-Origin', '*')

    def do_OPTIONS(self):
        self.send_response(200)
        origin = self.headers.get('Origin', '*')
        self.send_cors_headers(origin)
        self.end_headers()

    def check_auth(self, origin):
        global config
        token = config.get('token')
        allowed_origin = config.get('origin')
        
        if not token:
            return False, "Not paired yet"
            
        if allowed_origin != '*' and origin:
            origin_clean = origin.replace('https://', '').replace('http://', '').split('/')[0].split(':')[0]
            allowed_clean = allowed_origin.replace('https://', '').replace('http://', '').split('/')[0].split(':')[0]
            if allowed_clean not in origin_clean and origin_clean not in allowed_clean:
                return False, "Unauthorized Origin"
                
        auth_header = self.headers.get('Authorization', '')
        if not auth_header.startswith('Bearer '):
            return False, "Missing or invalid authorization header format"
            
        provided_token = auth_header.split(' ')[1]
        if provided_token != token:
            return False, "Invalid security token"
            
        return True, "Authorized"

    def respond_json(self, status_code, data, origin=None):
        self.send_response(status_code)
        self.send_header('Content-Type', 'application/json')
        self.send_cors_headers(origin)
        self.end_headers()
        self.wfile.write(json.dumps(data).encode('utf-8'))

    def do_GET(self):
        global config
        origin = self.headers.get('Origin', '')
        
        if self.path == '/status':
            is_paired = 'token' in config and config['token']
            self.respond_json(200, {
                'status': 'active',
                'paired': is_paired
            }, origin)
            return
            
        if self.path == '/printers':
            authorized, msg = self.check_auth(origin)
            if not authorized:
                self.respond_json(403, {'error': msg}, origin)
                return
                
            if win32print is None:
                printers = ["Mock Printer 1", "Mock Printer 2"]
            else:
                try:
                    flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
                    printer_tuples = win32print.EnumPrinters(flags, None, 1)
                    printers = [p[2] for p in printer_tuples]
                except Exception as e:
                    self.respond_json(500, {'error': f"Failed to list printers: {str(e)}"}, origin)
                    return
                    
            self.respond_json(200, {'printers': printers}, origin)
            return

        self.respond_json(404, {'error': 'Not found'}, origin)

    def do_POST(self):
        global config
        origin = self.headers.get('Origin', '')
        
        content_length = int(self.headers.get('Content-Length', 0))
        post_data = self.rfile.read(content_length)
        
        try:
            body = json.loads(post_data.decode('utf-8'))
        except Exception:
            self.respond_json(400, {'error': 'Invalid JSON body'}, origin)
            return

        if self.path == '/pair':
            if 'token' in config and config['token']:
                authorized, msg = self.check_auth(origin)
                if not authorized:
                    self.respond_json(403, {'error': 'Already paired. Re-pairing unauthorized.'}, origin)
                    return
            
            token = body.get('token')
            origin_site = body.get('origin')
            
            if not token or not origin_site:
                self.respond_json(400, {'error': 'token and origin are required'}, origin)
                return
                
            config['token'] = token
            config['origin'] = origin_site
            
            if save_config(config):
                self.respond_json(200, {'success': True, 'message': 'Successfully paired with Hızlı Kasa'}, origin)
            else:
                self.respond_json(500, {'error': 'Failed to save pairing configuration'}, origin)
            return

        if self.path == '/print':
            authorized, msg = self.check_auth(origin)
            if not authorized:
                self.respond_json(403, {'error': msg}, origin)
                return
                
            printer_name = body.get('printer_name')
            image_data = body.get('image')
            
            if not printer_name or not image_data:
                self.respond_json(400, {'error': 'printer_name and image are required'}, origin)
                return

            if win32print is None:
                print(f"Mock Printing to: {printer_name}")
                self.respond_json(200, {'success': True, 'message': 'Mock print successful'}, origin)
                return
                
            try:
                if ',' in image_data:
                    image_data = image_data.split(',')[1]
                    
                image_bytes = base64.b64decode(image_data)
                img = Image.open(BytesIO(image_bytes))
                
                rotate_angle = body.get('rotate', 0)
                if rotate_angle:
                    try:
                        angle = int(rotate_angle)
                        if angle in [90, 180, 270]:
                            img = img.rotate(angle, expand=True)
                    except Exception as ex:
                        print(f"Rotation error: {ex}")

                hprinter = win32print.OpenPrinter(printer_name)
                try:
                    hdc = win32ui.CreateDC()
                    hdc.CreatePrinterDC(printer_name)
                    
                    hdc.StartDoc("Hizli Kasa Print Job")
                    hdc.StartPage()
                    
                    printable_width = hdc.GetDeviceCaps(win32con.HORZRES)
                    img_w, img_h = img.size
                    
                    scale = printable_width / img_w
                    print_w = printable_width
                    print_h = int(img_h * scale)
                    
                    # High-quality resize before B&W conversion to prevent GDI scaling artifacts
                    try:
                        resample_filter = Image.Resampling.LANCZOS
                    except AttributeError:
                        resample_filter = Image.LANCZOS if hasattr(Image, 'LANCZOS') else Image.BICUBIC
                    
                    img_resized = img.resize((print_w, print_h), resample_filter)
                    
                    # Convert to grayscale and apply custom threshold
                    threshold = int(body.get('threshold', 180))
                    img_gray = img_resized.convert('L')
                    img_bw = img_gray.point(lambda x: 0 if x < threshold else 255, '1')
                    
                    dib = ImageWin.Dib(img_bw)
                    dib.draw(hdc.GetHandleAttrib(), (0, 0, print_w, print_h))
                    
                    hdc.EndPage()
                    hdc.EndDoc()
                finally:
                    win32print.ClosePrinter(hprinter)
                    
                self.respond_json(200, {'success': True, 'message': 'Print job sent successfully'}, origin)
                
            except Exception as e:
                self.respond_json(500, {'error': f"Printing failed: {str(e)}"}, origin)
            return

        self.respond_json(404, {'error': 'Not found'}, origin)


def create_tray_icon_image():
    image = Image.new('RGB', (64, 64), color='#00A32A')
    dc = ImageDraw.Draw(image)
    dc.rectangle([16, 24, 48, 52], fill='white')
    dc.line([20, 36, 44, 36], fill='black', width=3)
    dc.rectangle([22, 12, 42, 24], fill='white')
    dc.rectangle([24, 44, 40, 58], fill='white')
    dc.ellipse([20, 28, 24, 32], fill='#00A32A')
    return image

httpd_server = None

def exit_action(icon, item):
    global httpd_server
    print("Exiting Print Helper...")
    if httpd_server:
        threading.Thread(target=httpd_server.shutdown).start()
    icon.stop()

def run(server_class=HTTPServer, handler_class=PrintHelperHandler, start_port=5001):
    global httpd_server
    
    port = start_port
    max_port = start_port + 10
    bound = False
    
    # Try multiple ports dynamically if 5001 is already taken
    while port < max_port:
        try:
            server_address = ('127.0.0.1', port)
            httpd_server = server_class(server_address, handler_class)
            bound = True
            break
        except OSError:
            print(f"Port {port} busy, trying next port...")
            port += 1
            
    if not bound:
        print("Error: Could not bind to any port in range 5001-5010.")
        return
        
    server_thread = threading.Thread(target=httpd_server.serve_forever)
    server_thread.daemon = True
    server_thread.start()
    
    print(f"Hızlı Kasa Print Helper active on http://127.0.0.1:{port}")
    
    if pystray is not None:
        try:
            icon_image = create_tray_icon_image()
            icon = pystray.Icon(
                "hizli_kasa_print_helper",
                icon_image,
                title=f"Hızlı Kasa Yazdırma Yardımcısı (Port: {port})",
                menu=pystray.Menu(
                    pystray.MenuItem(f"Durum: Çalışıyor (Port: {port})", lambda: None, enabled=False),
                    pystray.MenuItem("Windows ile Birlikte Başlat", toggle_startup, checked=lambda item: is_startup_enabled()),
                    pystray.MenuItem("Kapat / Çıkış", exit_action)
                )
            )
            icon.run()
        except Exception as e:
            print(f"Error starting system tray icon: {e}")
            try:
                httpd_server.serve_forever()
            except KeyboardInterrupt:
                pass
    else:
        try:
            httpd_server.serve_forever()
        except KeyboardInterrupt:
            pass
            
    if httpd_server:
        httpd_server.server_close()

def kill_older_instances():
    my_pid = os.getpid()
    try:
        subprocess.run(f'taskkill /F /IM hizli-kasa-print-helper.exe /FI "PID ne {my_pid}"', shell=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        subprocess.run(f'taskkill /F /IM print_helper.exe /FI "PID ne {my_pid}"', shell=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception as e:
        print(f"Error killing older instances: {e}")

if __name__ == '__main__':
    ensure_startup_registered()
    kill_older_instances()
    run()
