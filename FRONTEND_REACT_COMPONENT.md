# 🚀 Reset Password Component - Ready to Copy & Paste

## React Implementation

```jsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

export function ResetPasswordFlow() {
  // States
  const [screen, setScreen] = useState('email'); // 'email' | 'otp' | 'password' | 'success'
  const [email, setEmail] = useState('');
  const [otp, setOtp] = useState(['', '', '', '', '', '']);
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [otpExpire, setOtpExpire] = useState(0);
  const [canResendOtp, setCanResendOtp] = useState(false);

  // Countdown timer untuk OTP
  useEffect(() => {
    if (otpExpire <= 0) return;

    const timer = setInterval(() => {
      setOtpExpire(prev => prev - 1);
    }, 1000);

    return () => clearInterval(timer);
  }, [otpExpire]);

  // Enable resend button setelah 1 menit
  useEffect(() => {
    if (otpExpire <= 60 * 9) {
      setCanResendOtp(true);
    }
  }, [otpExpire]);

  const api = axios.create({
    baseURL: '/api',
    timeout: 10000
  });

  // Format waktu display
  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  };

  // Screen 1: Request OTP
  const handleRequestOtp = async (e) => {
    e.preventDefault();
    setError('');

    if (!email || !email.includes('@')) {
      setError('Email harus valid');
      return;
    }

    try {
      setLoading(true);
      const response = await api.post('/request-otp-for-reset', { email });

      if (response.data.status === 'success') {
        setSuccess('OTP berhasil dikirim ke email Anda!');
        setScreen('otp');
        setOtpExpire(10 * 60); // 10 minutes
        setCanResendOtp(false);
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal request OTP');
    } finally {
      setLoading(false);
    }
  };

  // Screen 2: Handle OTP Input
  const handleOtpChange = (index, value) => {
    // Hanya angka
    if (!/^\d*$/.test(value)) return;

    const newOtp = [...otp];
    newOtp[index] = value;
    setOtp(newOtp);

    // Auto focus ke field berikutnya
    if (value && index < 5) {
      document.getElementById(`otp-${index + 1}`)?.focus();
    }
  };

  const handleOtpKeyDown = (index, e) => {
    // Backspace untuk kembali ke field sebelumnya
    if (e.key === 'Backspace' && !otp[index] && index > 0) {
      document.getElementById(`otp-${index - 1}`)?.focus();
    }
  };

  const handleVerifyOtp = async () => {
    const otpCode = otp.join('');
    
    if (otpCode.length !== 6) {
      setError('OTP harus 6 digit');
      return;
    }

    try {
      setLoading(true);
      setError('');
      const response = await api.post('/verify-otp', {
        email,
        otp: otpCode
      });

      if (response.data.status === 'success') {
        setSuccess('OTP valid! Silakan buat password baru.');
        setScreen('password');
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'OTP tidak valid');
    } finally {
      setLoading(false);
    }
  };

  const handleResendOtp = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await api.post('/request-otp-for-reset', { email });

      if (response.data.status === 'success') {
        setOtp(['', '', '', '', '', '']);
        setOtpExpire(10 * 60);
        setCanResendOtp(false);
        setSuccess('OTP baru berhasil dikirim!');
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err) {
      setError('Gagal kirim OTP ulang');
    } finally {
      setLoading(false);
    }
  };

  // Screen 3: Reset Password
  const handleResetPassword = async (e) => {
    e.preventDefault();
    setError('');

    if (!password || password.length < 6) {
      setError('Password minimal 6 karakter');
      return;
    }

    if (password !== passwordConfirm) {
      setError('Password tidak cocok');
      return;
    }

    try {
      setLoading(true);
      const response = await api.post('/reset-password-with-otp', {
        email,
        otp: otp.join(''),
        password,
        password_confirmation: passwordConfirm
      });

      if (response.data.status === 'success') {
        setScreen('success');
        setTimeout(() => {
          window.location.href = '/login';
        }, 2000);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal reset password');
    } finally {
      setLoading(false);
    }
  };

  // UI Components
  return (
    <div className="reset-password-container">
      {/* Error Alert */}
      {error && (
        <div className="alert alert-error">
          <p>{error}</p>
          <button onClick={() => setError('')}>×</button>
        </div>
      )}

      {/* Success Alert */}
      {success && (
        <div className="alert alert-success">
          <p>{success}</p>
        </div>
      )}

      {/* Screen 1: Email */}
      {screen === 'email' && (
        <div className="screen">
          <h2>🔑 Lupa Password?</h2>
          <p>Masukkan email Anda untuk menerima kode OTP</p>
          
          <form onSubmit={handleRequestOtp}>
            <input
              type="email"
              placeholder="📧 user@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              disabled={loading}
            />
            
            <button type="submit" disabled={loading || !email}>
              {loading ? 'Mengirim...' : 'Kirim Kode OTP'}
            </button>
          </form>

          <a href="/login" className="back-link">← Kembali ke Login</a>
        </div>
      )}

      {/* Screen 2: OTP */}
      {screen === 'otp' && (
        <div className="screen">
          <h2>✉️ Kode OTP Terkirim</h2>
          <p>Kode OTP sudah dikirim ke:<br /><strong>{email}</strong></p>

          <div className="otp-input-group">
            <label>Masukkan 6 digit kode:</label>
            <div className="otp-inputs">
              {otp.map((digit, index) => (
                <input
                  key={index}
                  id={`otp-${index}`}
                  type="text"
                  maxLength="1"
                  value={digit}
                  onChange={(e) => handleOtpChange(index, e.target.value)}
                  onKeyDown={(e) => handleOtpKeyDown(index, e)}
                  disabled={loading}
                  className="otp-digit"
                />
              ))}
            </div>
          </div>

          <div className="expire-timer">
            ⏱️ Kadaluarsa dalam: <strong>{formatTime(otpExpire)}</strong>
          </div>

          <button 
            className="btn-primary" 
            onClick={handleVerifyOtp}
            disabled={loading || otp.join('').length !== 6}
          >
            {loading ? 'Verifikasi...' : 'Verifikasi OTP'}
          </button>

          <button
            className="btn-secondary"
            onClick={handleResendOtp}
            disabled={!canResendOtp || loading}
          >
            {canResendOtp ? 'Kirim Ulang Kode' : `Kirim Ulang (${formatTime(300 - otpExpire)})`}
          </button>
        </div>
      )}

      {/* Screen 3: Password */}
      {screen === 'password' && (
        <div className="screen">
          <h2>✅ OTP Terverifikasi</h2>
          <p>Buat Password Baru</p>

          <form onSubmit={handleResetPassword}>
            <div className="password-input-group">
              <label>Password Baru</label>
              <div className="password-field">
                <input
                  type={showPassword ? 'text' : 'password'}
                  placeholder="🔒 Minimal 6 karakter"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  disabled={loading}
                />
                <button
                  type="button"
                  className="toggle-password"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? '👁️' : '👁️‍🗨️'}
                </button>
              </div>
              <div className="password-strength">
                {password.length >= 6 && '✅ Password cukup kuat'}
              </div>
            </div>

            <div className="password-input-group">
              <label>Konfirmasi Password</label>
              <input
                type={showPassword ? 'text' : 'password'}
                placeholder="🔒 Ulangi password"
                value={passwordConfirm}
                onChange={(e) => setPasswordConfirm(e.target.value)}
                disabled={loading}
              />
              {password && passwordConfirm && password === passwordConfirm && (
                <div className="match-indicator">✅ Password cocok</div>
              )}
            </div>

            <button 
              type="submit" 
              disabled={loading || password.length < 6 || password !== passwordConfirm}
            >
              {loading ? 'Menyimpan...' : 'Simpan Password'}
            </button>
          </form>
        </div>
      )}

      {/* Screen 4: Success */}
      {screen === 'success' && (
        <div className="screen success-screen">
          <h2>✅ BERHASIL!</h2>
          <p>Password Anda telah diubah</p>
          <p>Silakan login dengan password baru Anda</p>
          <p className="redirect-message">Mengarahkan ke halaman login...</p>
        </div>
      )}
    </div>
  );
}

export default ResetPasswordFlow;
```

---

## CSS Styling (Tailwind/Custom)

```css
.reset-password-container {
  max-width: 400px;
  margin: 0 auto;
  padding: 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert-error {
  background-color: #fee;
  border: 1px solid #f88;
  color: #c33;
}

.alert-success {
  background-color: #efe;
  border: 1px solid #8f8;
  color: #3c3;
}

.screen {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.screen h2 {
  font-size: 24px;
  margin-bottom: 8px;
  color: #333;
}

.screen p {
  color: #666;
  margin-bottom: 20px;
  font-size: 14px;
}

input[type="email"],
input[type="password"],
input[type="text"] {
  width: 100%;
  padding: 12px;
  margin-bottom: 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 16px;
}

input:focus {
  outline: none;
  border-color: #4CAF50;
  box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.otp-inputs {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 20px;
}

.otp-digit {
  width: 40px !important;
  height: 40px !important;
  padding: 0 !important;
  margin: 0 !important;
  text-align: center;
  font-size: 18px;
  font-weight: bold;
  border: 2px solid #ddd !important;
}

.otp-digit:focus {
  border-color: #4CAF50 !important;
}

.password-field {
  position: relative;
  margin-bottom: 12px;
}

.toggle-password {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  padding: 0;
}

button[type="submit"],
button[type="button"].btn-primary {
  background-color: #4CAF50;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  cursor: pointer;
  width: 100%;
  margin-bottom: 12px;
}

button:hover:not(:disabled) {
  background-color: #45a049;
}

button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
  padding: 12px 24px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  width: 100%;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #e0e0e0;
}

.expire-timer {
  color: #FF9800;
  font-weight: bold;
  margin-bottom: 20px;
}

.password-strength,
.match-indicator {
  color: #4CAF50;
  font-size: 12px;
  margin-top: 4px;
}

.back-link {
  display: inline-block;
  margin-top: 16px;
  color: #4CAF50;
  text-decoration: none;
  font-size: 14px;
}

.back-link:hover {
  text-decoration: underline;
}

.success-screen {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.success-screen h2,
.success-screen p {
  color: white;
}

.redirect-message {
  font-size: 12px;
  margin-top: 20px;
  opacity: 0.8;
}
```

---

## Usage

```jsx
import ResetPasswordFlow from './components/ResetPasswordFlow';

function App() {
  return (
    <div className="app">
      <ResetPasswordFlow />
    </div>
  );
}
```

---

**Ready to implement!** Copy & paste langsung ke project Anda 🚀
