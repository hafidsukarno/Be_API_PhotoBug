# 📱 Frontend Guide: Reset Password dengan OTP

## 🎯 Alur Tampilan (Mobile-First)

### **Screen 1: Lupa Password / Input Email**

```
┌─────────────────────────────────┐
│                                 │
│    🔑 Lupa Password?            │
│                                 │
│  Masukkan email Anda untuk      │
│  menerima kode OTP              │
│                                 │
│  ┌──────────────────────────┐   │
│  │ 📧 user@example.com     │   │
│  │                          │   │
│  └──────────────────────────┘   │
│                                 │
│  ┌──────────────────────────┐   │
│  │  Kirim Kode OTP          │   │
│  │  (disabled saat loading) │   │
│  └──────────────────────────┘   │
│                                 │
│  Kembali ke Login               │
│                                 │
└─────────────────────────────────┘
```

**State:**
- Input email required
- Button disabled sampai email valid
- Loading spinner saat request
- Show error message jika email tidak terdaftar

---

### **Screen 2: Input OTP (6 Digit)**

```
┌─────────────────────────────────┐
│                                 │
│  ✉️ Kode OTP Terkirim           │
│                                 │
│  Kode OTP sudah dikirim ke:     │
│  user@***mple.com               │
│                                 │
│  Masukkan 6 digit kode:         │
│  ┌─┬─┬─┬─┬─┬─┐                 │
│  │1│2│3│4│5│6│  (input field)  │
│  └─┴─┴─┴─┴─┴─┘                 │
│                                 │
│  ⏱️ Expire dalam: 09:45          │
│                                 │
│  ┌──────────────────────────┐   │
│  │  Verifikasi OTP          │   │
│  └──────────────────────────┘   │
│                                 │
│  Kirim ulang kode (60 detik)    │
│                                 │
└─────────────────────────────────┘
```

**State:**
- Auto-focus pada digit pertama
- Move ke digit berikutnya otomatis saat input
- Countdown timer (10 menit)
- Button "Kirim Ulang" disabled selama countdown
- Show error jika OTP invalid

---

### **Screen 3: Reset Password Baru**

```
┌─────────────────────────────────┐
│                                 │
│  ✅ OTP Terverifikasi           │
│                                 │
│  Buat Password Baru             │
│                                 │
│  ┌──────────────────────────┐   │
│  │ 🔒 Password Baru        │   │
│  │ [••••••••••]             │   │
│  │ ☑ Tampilkan Password     │   │
│  └──────────────────────────┘   │
│                                 │
│  ┌──────────────────────────┐   │
│  │ 🔒 Konfirmasi Password  │   │
│  │ [••••••••••]             │   │
│  │                          │   │
│  └──────────────────────────┘   │
│                                 │
│  ☑ Password minimal 6 karakter  │
│  ☑ Password cocok               │
│                                 │
│  ┌──────────────────────────┐   │
│  │  Simpan Password         │   │
│  └──────────────────────────┘   │
│                                 │
└─────────────────────────────────┘
```

**State:**
- Password strength indicator
- Toggle show/hide password
- Validasi real-time untuk password cocok
- Button simpan disabled sampai valid
- Show success modal setelah berhasil

---

### **Screen 4: Success Confirmation**

```
┌─────────────────────────────────┐
│                                 │
│         ✅ BERHASIL!            │
│                                 │
│  Password Anda telah diubah     │
│                                 │
│  Silakan login dengan password  │
│  baru Anda                      │
│                                 │
│  ┌──────────────────────────┐   │
│  │  Kembali ke Login        │   │
│  └──────────────────────────┘   │
│                                 │
└─────────────────────────────────┘
```

---

## 🔌 API Integration

### **Endpoint 1: Request OTP**
```
POST /api/request-otp-for-reset
Content-Type: application/json

{
  "email": "user@example.com"
}
```

**Response Success (200):**
```json
{
  "status": "success",
  "message": "OTP sudah dikirim ke email Anda. Berlaku 10 menit.",
  "data": {
    "email": "user@example.com",
    "name": "Budi Santoso"
  }
}
```

**Response Error (403):**
```json
{
  "status": "error",
  "message": "Email tidak terdaftar sebagai akun Petani."
}
```

**Response Error (422):**
```json
{
  "status": "error",
  "message": "Validasi gagal",
  "errors": {
    "email": ["Email harus valid"]
  }
}
```

---

### **Endpoint 2: Verify OTP (Optional)**
```
POST /api/verify-otp
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Response Success (200):**
```json
{
  "status": "success",
  "message": "OTP valid. Silakan reset password Anda.",
  "data": {
    "email": "user@example.com"
  }
}
```

**Response Error (400):**
```json
{
  "status": "error",
  "message": "OTP tidak valid atau sudah kadaluarsa"
}
```

---

### **Endpoint 3: Reset Password dengan OTP**
```
POST /api/reset-password-with-otp
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "123456",
  "password": "password_baru",
  "password_confirmation": "password_baru"
}
```

**Response Success (200):**
```json
{
  "status": "success",
  "message": "Password berhasil diubah. Silakan login dengan password baru."
}
```

**Response Error (400):**
```json
{
  "status": "error",
  "message": "OTP tidak valid atau sudah kadaluarsa"
}
```

---

## 💻 Contoh Implementasi (React/Vue)

### **Using Fetch API**

```javascript
// Step 1: Request OTP
async function requestOTP(email) {
  try {
    setLoading(true);
    const response = await fetch('/api/request-otp-for-reset', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email })
    });

    const data = await response.json();

    if (response.ok && data.status === 'success') {
      // Show OTP screen
      setCurrentScreen('otp');
      setUserEmail(email);
      setOtpExpireTime(10 * 60); // 10 minutes in seconds
      showSuccessToast('OTP berhasil dikirim ke email Anda');
    } else {
      showErrorToast(data.message);
    }
  } catch (error) {
    showErrorToast('Gagal request OTP: ' + error.message);
  } finally {
    setLoading(false);
  }
}

// Step 2: Verify OTP
async function verifyOTP(email, otp) {
  try {
    setLoading(true);
    const response = await fetch('/api/verify-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email, otp })
    });

    const data = await response.json();

    if (response.ok && data.status === 'success') {
      // Show reset password screen
      setCurrentScreen('resetPassword');
      showSuccessToast('OTP valid!');
    } else {
      showErrorToast(data.message);
    }
  } catch (error) {
    showErrorToast('Gagal verify OTP: ' + error.message);
  } finally {
    setLoading(false);
  }
}

// Step 3: Reset Password
async function resetPassword(email, otp, password, passwordConfirmation) {
  try {
    setLoading(true);
    const response = await fetch('/api/reset-password-with-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email,
        otp,
        password,
        password_confirmation: passwordConfirmation
      })
    });

    const data = await response.json();

    if (response.ok && data.status === 'success') {
      // Show success screen
      setCurrentScreen('success');
      showSuccessToast(data.message);
      
      // Redirect to login after 2 seconds
      setTimeout(() => {
        window.location.href = '/login';
      }, 2000);
    } else {
      showErrorToast(data.message);
    }
  } catch (error) {
    showErrorToast('Gagal reset password: ' + error.message);
  } finally {
    setLoading(false);
  }
}
```

---

### **Using Axios**

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: '/api'
});

// Request OTP
const requestOTP = (email) => {
  return api.post('/request-otp-for-reset', { email });
};

// Verify OTP
const verifyOTP = (email, otp) => {
  return api.post('/verify-otp', { email, otp });
};

// Reset Password
const resetPassword = (email, otp, password, passwordConfirmation) => {
  return api.post('/reset-password-with-otp', {
    email,
    otp,
    password,
    password_confirmation: passwordConfirmation
  });
};

// Usage in component
async function handleResetPassword() {
  try {
    const response = await resetPassword(email, otp, password, passwordConfirmation);
    if (response.data.status === 'success') {
      showSuccessModal();
      setTimeout(() => navigate('/login'), 2000);
    }
  } catch (error) {
    showErrorToast(error.response?.data?.message || 'Terjadi kesalahan');
  }
}
```

---

## ⏱️ Countdown Timer Implementation

```javascript
// Countdown untuk OTP expiry
useEffect(() => {
  if (otpExpireTime <= 0) return;

  const interval = setInterval(() => {
    setOtpExpireTime(prev => prev - 1);
  }, 1000);

  return () => clearInterval(interval);
}, [otpExpireTime]);

// Format time display
const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

// Usage in JSX
<div>Expire dalam: {formatTime(otpExpireTime)}</div>
```

---

## ✅ Form Validation Rules

```javascript
const validateEmail = (email) => {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
};

const validateOTP = (otp) => {
  return /^\d{6}$/.test(otp); // Must be 6 digits
};

const validatePassword = (password) => {
  return password.length >= 6; // Minimum 6 characters
};

const validatePasswordMatch = (password, confirmation) => {
  return password === confirmation;
};
```

---

## 🎨 Loading & Error States

**Loading Spinner:**
- Show saat API request
- Disable input & button selama loading

**Error Toast:**
- Position: top-right atau bottom-center
- Auto-dismiss setelah 3-4 detik
- Show error message dari API response

**Success Modal:**
- Show setelah password berhasil direset
- Auto-redirect ke login setelah 2 detik

---

## 🔒 Security Tips

1. **HTTPS Only** - Pastikan semua request via HTTPS
2. **No Console Logging** - Jangan log password/OTP di console
3. **Clear Sensitive Data** - Bersihkan password dari state setelah submit
4. **Timeout** - Implementasikan timeout untuk request
5. **Rate Limiting** - Backend sudah handle, pastikan frontend juga validasi

---

## 📋 Checklist Frontend

- [ ] Screen untuk input email
- [ ] Loading state saat request OTP
- [ ] Error handling & display
- [ ] OTP input field (6 digit)
- [ ] Countdown timer
- [ ] Resend OTP button (with cooldown)
- [ ] Password input dengan show/hide toggle
- [ ] Password validation indicator
- [ ] Reset password submit
- [ ] Success modal dengan auto-redirect
- [ ] Mobile responsive design
- [ ] Form validation real-time

---

## 🚀 Ready to Code!

Copy semua ini, share ke frontend team, dan mereka bisa langsung mulai ngoding!

Questions? Hubungi backend team untuk clarification.
