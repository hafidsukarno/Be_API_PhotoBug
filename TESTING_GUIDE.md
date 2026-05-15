# PhotoBug API Testing Guide

## Quick Start Testing

### 1. Start Development Server
```bash
php artisan serve
```
Server akan run di `http://localhost:8000`

### 2. Test Authentication

#### Register Petani Baru
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Budi Petani",
    "username": "budi_petani",
    "email": "budi@petani.com",
    "password": "password123",
    "password_confirmation": "password123",
    "no_hp": "081234567890",
    "village_id": 1
  }'
```

#### Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "budi_petani",
    "password": "password123"
  }'
```
Save the `token` dari response untuk request selanjutnya.

#### Get Current User
```bash
curl -X GET http://localhost:8000/api/user \
  -H "Authorization: Bearer {TOKEN}"
```

---

## Testing Petani Endpoints

### 1. Upload Detection
```bash
curl -X POST http://localhost:8000/api/petani/detections \
  -H "Authorization: Bearer {TOKEN}" \
  -F "image=@/path/to/image.jpg" \
  -F "description=Tanaman padi terserang hama" \
  -F "latitude=-6.8957" \
  -F "longitude=107.6061"
```

### 2. Get History
```bash
# Semua deteksi
curl -X GET http://localhost:8000/api/petani/history \
  -H "Authorization: Bearer {TOKEN}"

# Filter by status
curl -X GET "http://localhost:8000/api/petani/history?status=pending" \
  -H "Authorization: Bearer {TOKEN}"

curl -X GET "http://localhost:8000/api/petani/history?status=completed" \
  -H "Authorization: Bearer {TOKEN}"
```

### 3. Get Detection Detail
```bash
curl -X GET http://localhost:8000/api/petani/detections/1 \
  -H "Authorization: Bearer {TOKEN}"
```

---

## Testing Penyuluh Endpoints

### Prerequisite:
Login dengan penyuluh account
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "penyuluh1@penyuluh.com",
    "password": "password123"
  }'
```

### 1. Get Detections List
```bash
# Semua deteksi dari petani di desa yang ditangani
curl -X GET http://localhost:8000/api/penyuluh/detections \
  -H "Authorization: Bearer {TOKEN}"

# Filter by status pending
curl -X GET "http://localhost:8000/api/penyuluh/detections?status=pending" \
  -H "Authorization: Bearer {TOKEN}"

# Filter by status completed
curl -X GET "http://localhost:8000/api/penyuluh/detections?status=completed" \
  -H "Authorization: Bearer {TOKEN}"
```

### 2. Get Detection Detail
```bash
curl -X GET http://localhost:8000/api/penyuluh/detections/1 \
  -H "Authorization: Bearer {TOKEN}"
```

### 3. Submit Recommendation
```bash
curl -X POST http://localhost:8000/api/penyuluh/detections/1/recommendations \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "recommendation_text": "Hama wereng dapat dibasmi dengan insektisida biologis. Seprotkan setiap 7 hari selama 3 minggu."
  }'
```

---

## Testing Admin Endpoints

### Prerequisite:
Login dengan admin account
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "superadmin@admin.com",
    "password": "password123"
  }'
```

### 1. Villages Management

#### Get All Villages
```bash
curl -X GET http://localhost:8000/api/admin/villages \
  -H "Authorization: Bearer {TOKEN}"
```

#### Create Village
```bash
curl -X POST http://localhost:8000/api/admin/villages \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "village_name": "Desa Baru",
    "district": "Kecamatan Baru"
  }'
```

#### Update Village
```bash
curl -X PUT http://localhost:8000/api/admin/villages/1 \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "village_name": "Desa Updated",
    "district": "Kecamatan Updated"
  }'
```

#### Delete Village
```bash
curl -X DELETE http://localhost:8000/api/admin/villages/1 \
  -H "Authorization: Bearer {TOKEN}"
```

### 2. Penyuluh Management

#### Get All Penyuluh
```bash
curl -X GET http://localhost:8000/api/admin/penyuluh \
  -H "Authorization: Bearer {TOKEN}"
```

#### Create Penyuluh
```bash
curl -X POST http://localhost:8000/api/admin/penyuluh \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Penyuluh Baru",
    "username": "penyuluh_baru",
    "email": "penyuluh_baru@penyuluh.com",
    "password": "password123",
    "no_hp": "081234567890",
    "villages": [1, 2]
  }'
```

#### Update Penyuluh
```bash
curl -X PUT http://localhost:8000/api/admin/penyuluh/1 \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Penyuluh Updated",
    "villages": [1, 2, 3]
  }'
```

#### Delete Penyuluh
```bash
curl -X DELETE http://localhost:8000/api/admin/penyuluh/1 \
  -H "Authorization: Bearer {TOKEN}"
```

---

## Testing with Postman

1. Import the API_DOCUMENTATION.md endpoints into Postman
2. Create a Postman Environment variable `{{token}}` 
3. After login, set: `pm.environment.set("token", pm.response.json().token);`
4. Use `{{token}}` in Authorization header for authenticated requests

---

## Database Reset
```bash
php artisan migrate:fresh --seed
```

---

## Useful Commands

### Check Routes
```bash
php artisan route:list
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
```

### View Database
```bash
# Using MySQL CLI
mysql -u root -p be_photobug
```

### Test Specific Endpoint
```bash
php artisan tinker
>>> $user = User::first();
>>> $user->managedVillages()->get();
```
