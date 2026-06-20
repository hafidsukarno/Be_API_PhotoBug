# 🔐 Reset Password OTP - API Documentation

**Base URL:** `http://localhost:8000/api`

---

## 📍 Endpoints

### 1️⃣ Request OTP

```http
POST /request-otp-for-reset
Content-Type: application/json

{
  "email": "user@example.com"
}
```

**Response 200 OK:**
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

**Error 403:**
```json
{
  "status": "error",
  "message": "Email tidak terdaftar sebagai akun Petani."
}
```

---

### 2️⃣ Verify OTP (Optional)

```http
POST /verify-otp
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Response 200 OK:**
```json
{
  "status": "success",
  "message": "OTP valid. Silakan reset password Anda.",
  "data": {
    "email": "user@example.com"
  }
}
```

**Error 400:**
```json
{
  "status": "error",
  "message": "OTP tidak valid atau sudah kadaluarsa"
}
```

---

### 3️⃣ Reset Password dengan OTP

```http
POST /reset-password-with-otp
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "123456",
  "password": "password_baru",
  "password_confirmation": "password_baru"
}
```

**Response 200 OK:**
```json
{
  "status": "success",
  "message": "Password berhasil diubah. Silakan login dengan password baru."
}
```

**Error 400:**
```json
{
  "status": "error",
  "message": "OTP tidak valid atau sudah kadaluarsa"
}
```

**Error 422:**
```json
{
  "status": "error",
  "message": "Validasi gagal",
  "errors": {
    "password": ["Password harus minimal 6 karakter"]
  }
}
```

---

## 🔄 Complete Flow

```
1. User input email
   ↓
2. POST /request-otp-for-reset
   ├─ Success: Show OTP input screen
   └─ Error: Show error message
   ↓
3. User input 6 digit OTP
   ↓
4. POST /reset-password-with-otp
   ├─ Success: Show success modal → Redirect login
   └─ Error: Show error message & ask resend OTP
```

---

## ⚙️ Details

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| email | string | Yes | Must be valid email & registered Petani |
| otp | string | Yes | Must be exactly 6 digits |
| password | string | Yes | Minimum 6 characters |
| password_confirmation | string | Yes | Must match password |

---

## ⏰ OTP Validity

- **Duration:** 10 minutes
- **Format:** 6 random digits (000000-999999)
- **Max Attempts:** Unlimited
- **Reusable:** No (mark as used after successful reset)

---

## 🔗 cURL Examples

### Request OTP
```bash
curl -X POST http://localhost:8000/api/request-otp-for-reset \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

### Verify OTP
```bash
curl -X POST http://localhost:8000/api/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","otp":"123456"}'
```

### Reset Password
```bash
curl -X POST http://localhost:8000/api/reset-password-with-otp \
  -H "Content-Type: application/json" \
  -d '{
    "email":"user@example.com",
    "otp":"123456",
    "password":"newpass123",
    "password_confirmation":"newpass123"
  }'
```

---

## 📝 Frontend Checklist

- [ ] Input email form
- [ ] Load spinner saat request
- [ ] Show email confirmation
- [ ] 6-digit OTP input (auto-advance)
- [ ] 10-minute countdown timer
- [ ] "Resend OTP" button with cooldown
- [ ] Password input with strength indicator
- [ ] Success confirmation with redirect

---

**Created:** 2026-06-20
**Status:** Ready for Implementation ✅
