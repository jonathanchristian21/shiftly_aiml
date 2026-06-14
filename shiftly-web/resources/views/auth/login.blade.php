<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Shiftly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'JetBrains Mono', 'SF Mono', monospace;
            -webkit-font-smoothing: antialiased;
        }
        
        body {
            background: #FAFAFA;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
        }
        
        .logo-box {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        input {
            transition: all 0.15s ease;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            background: white;
        }
        
        input:focus {
            outline: none;
            border-color: #007AFF;
            box-shadow: 0 0 0 4px rgba(0,122,255,0.08);
        }
        
        .btn-login {
            background: #007AFF;
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.15s ease;
            border: none;
        }
        
        .btn-login:hover {
            background: #0051D5;
            box-shadow: 0 4px 12px rgba(0,122,255,0.3);
        }
        
        .version {
            font-size: 11px;
            color: #86868B;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card w-full max-w-md p-10">
            <div class="text-center mb-8">
                <div class="logo-box mb-4">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h1 style="font-size: 28px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px;">Shiftly</h1>
                <p class="version">Hospital Scheduling System v1.0</p>
            </div>

            @if($errors->any())
                <div style="background: rgba(255,59,48,0.08); border: 1px solid rgba(255,59,48,0.2); border-radius: 10px; padding: 12px; margin-bottom: 20px; font-size: 13px; color: #C82333;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" style="margin-bottom: 20px;">
                @csrf
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #000; margin-bottom: 8px; letter-spacing: 0.02em; text-transform: uppercase;">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus style="width: 100%;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #000; margin-bottom: 8px; letter-spacing: 0.02em; text-transform: uppercase;">Password</label>
                    <input type="password" name="password" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 20px; display: flex; align-items: center;">
                    <input type="checkbox" name="remember" id="remember" style="width: 16px; height: 16px; margin-right: 8px; padding: 0;">
                    <label for="remember" style="font-size: 13px; color: #6E6E73; margin: 0;">Keep me signed in</label>
                </div>

                <button type="submit" class="btn-login" style="width: 100%; cursor: pointer;">
                    SIGN IN
                </button>
            </form>
            
            <div style="text-align: center; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.06);">
                <div style="font-size: 11px; color: #86868B; line-height: 1.6;">
                    <div style="margin-bottom: 4px;">Default Manager:</div>
                    <code style="background: #F5F5F7; padding: 2px 8px; border-radius: 4px; font-size: 10px;">manager@shiftly.com</code>
                    <span style="margin: 0 6px; color: #D1D1D6;">•</span>
                    <code style="background: #F5F5F7; padding: 2px 8px; border-radius: 4px; font-size: 10px;">password</code>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
