import os
import json
import base64
import sys
from io import BytesIO
from http.server import HTTPServer, BaseHTTPRequestHandler

# Import windows specific printing libraries
try:
    import win32print
    import win32ui
    import win32con
    from PIL import Image, ImageWin
except ImportError:
    print("Warning: Windows printing libraries not found. Running in mock/development mode.")
    win32print = None

# Config location: %APPDATA%/HizliKasa/config.json
APPDATA_DIR = os.path.join(os.environ.get('APPDATA', ''), 'HizliKasa')
CONFIG_PATH = os.path.join(APPDATA_DIR, 'config.json')

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
        # Silence default terminal logs to keep console clean
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
        # Verify Token-based Handshake
        global config
        
        # 1. Check if token is registered
        token = config.get('token')
        allowed_origin = config.get('origin')
        
        if not token:
            # Not paired yet
            return False, "Not paired yet"
            
        # 2. Check Origin (CORS security)
        # Check if the origin matches or starts with the registered origin (handling subdomains or www)
        if allowed_origin != '*' and origin:
            origin_clean = origin.replace('https://', '').replace('http://', '').split('/')[0].split(':')[0]
            allowed_clean = allowed_origin.replace('https://', '').replace('http://', '').split('/')[0].split(':')[0]
            if allowed_clean not in origin_clean and origin_clean not in allowed_clean:
                return False, "Unauthorized Origin"
                
        # 3. Check Authorization header
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
            # Check authentication
            authorized, msg = self.check_auth(origin)
            if not authorized:
                self.respond_json(403, {'error': msg}, origin)
                return
                
            # Fetch local Windows printers
            if win32print is None:
                printers = ["Mock Printer 1", "Mock Printer 2"]
            else:
                try:
                    # Enumerate local and network connection printers
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

        # 1. Pairing endpoint (Allows initial handshake)
        if self.path == '/pair':
            # If already paired, we don't allow re-pairing without authentication
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

        # 2. Printing endpoint
        if self.path == '/print':
            authorized, msg = self.check_auth(origin)
            if not authorized:
                self.respond_json(403, {'error': msg}, origin)
                return
                
            printer_name = body.get('printer_name')
            image_data = body.get('image') # Base64 PNG image string
            
            if not printer_name or not image_data:
                self.respond_json(400, {'error': 'printer_name and image are required'}, origin)
                return

            if win32print is None:
                # Mock print success for dev mode
                print(f"Mock Printing to: {printer_name}")
                self.respond_json(200, {'success': True, 'message': 'Mock print successful'}, origin)
                return
                
            try:
                # Clean base64 header if present
                if ',' in image_data:
                    image_data = image_data.split(',')[1]
                    
                image_bytes = base64.b64decode(image_data)
                img = Image.open(BytesIO(image_bytes))
                
                # Perform silent printing via GDI
                hprinter = win32print.OpenPrinter(printer_name)
                try:
                    hdc = win32ui.CreateDC()
                    hdc.CreatePrinterDC(printer_name)
                    
                    hdc.StartDoc("Hizli Kasa Print Job")
                    hdc.StartPage()
                    
                    # Calculate printable area dimensions
                    printable_width = hdc.GetDeviceCaps(win32con.HORZRES)
                    img_w, img_h = img.size
                    
                    # Scale image to fit printer width while maintaining aspect ratio
                    scale = printable_width / img_w
                    print_w = printable_width
                    print_h = int(img_h * scale)
                    
                    # Draw DIB (Device Independent Bitmap) directly to printer DC
                    dib = ImageWin.Dib(img)
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


def run(server_class=HTTPServer, handler_class=PrintHelperHandler, port=5001):
    server_address = ('127.0.0.1', port)
    httpd = server_class(server_address, handler_class)
    print(f"Hızlı Kasa Print Helper active on http://127.0.0.1:{port}")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        httpd.server_close()

if __name__ == '__main__':
    run()
