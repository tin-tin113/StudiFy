import subprocess
import sys

files = [
    r"c:\laragon\www\Studify\config\db.php",
    r"c:\laragon\www\Studify\includes\auth.php",
    r"c:\laragon\www\Studify\includes\functions.php",
    r"c:\laragon\www\Studify\auth\login.php",
    r"c:\laragon\www\Studify\auth\register.php",
    r"c:\laragon\www\Studify\auth\reset_password.php",
    r"c:\laragon\www\Studify\auth\forgot_password.php",
    r"c:\laragon\www\Studify\auth\profile.php",
    r"c:\laragon\www\Studify\admin\system_settings.php",
    r"c:\laragon\www\Studify\admin\admin_dashboard.php",
    r"c:\laragon\www\Studify\admin\manage_users.php",
    r"c:\laragon\www\Studify\student\tasks.php",
    r"c:\laragon\www\Studify\student\daily_planning.php",
    r"c:\laragon\www\Studify\student\buddy_messenger.php",
    r"c:\laragon\www\Studify\includes\header.php",
    r"c:\laragon\www\Studify\setup.php",
]

for i, file in enumerate(files, 1):
    print(f"\n{'='*60}")
    print(f"File {i}: {file.split(chr(92))[-1]}")
    print('='*60)
    try:
        result = subprocess.run(['php', '-l', file], capture_output=True, text=True, timeout=10)
        if result.stdout:
            print(result.stdout.strip())
        if result.stderr:
            print("STDERR:", result.stderr.strip())
    except Exception as e:
        print(f"Error running command: {e}")
