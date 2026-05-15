# PhotoBug API Documentation

## Overview
REST API untuk Sistem Deteksi Hama Tanaman berbasis Laravel 11 dengan role-based access control untuk 3 tipe user: Admin, Penyuluh (Extension Worker), dan Petani (Farmer).

## Authentication
Semua endpoint (kecuali register, login, reset-password) memerlukan token authentication menggunakan Laravel Sanctum.
Token dikirim melalui header: `Authorization: Bearer {token}`

---

## Public Endpoints (No Auth Required)

### 1. Register User (Petani)
- **Endpoint:** `POST /api/register`
- **Description:** Registrasi akun petani baru
- **Request Body:**
  ```json
  {
    "name": "string (required)",
    "username": "string (required, unique)",
    "email": "string (required, email, unique)",
    "password": "string (required, min:6, confirmed)",
    "no_hp": "string (optional)",
    "village_id": "integer (optional)"
  }
  ```
- **Response (201):**
  ```json
  {
    "message": "User registered successfully",
    "token": "string",
    "user": { user_object }
  }
  ```

### 2. Login
- **Endpoint:** `POST /api/login`
- **Description:** Login dengan username dan password
- **Request Body:**
  ```json
  {
    "username": "string (required)",
    "password": "string (required)"
  }
  ```
- **Response (200):**
  ```json
  {
    "message": "Login successful",
    "token": "string",
    "user": { user_object }
  }
  ```

### 3. Reset Password
- **Endpoint:** `POST /api/reset-password`
- **Description:** Reset password dengan email
- **Request Body:**
  ```json
  {
    "email": "string (required, exists in users)",
    "password": "string (required, min:6, confirmed)"
  }
  ```
- **Response (200):**
  ```json
  {
    "message": "Password reset successfully"
  }
  ```

---

## Authenticated Endpoints

### Get Current User
- **Endpoint:** `GET /api/user`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Mendapatkan data user yang sedang login beserta role, village, dan managedVillages
- **Response (200):**
  ```json
  {
    "id": "integer",
    "name": "string",
    "username": "string",
    "email": "string",
    "no_hp": "string or null",
    "role_id": "integer",
    "village_id": "integer or null",
    "role": { role_object },
    "village": { village_object } or null,
    "managedVillages": [ village_array ]
  }
  ```

### Logout
- **Endpoint:** `POST /api/logout`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Logout dan invalidate token
- **Response (200):**
  ```json
  {
    "message": "Logged out successfully"
  }
  ```

---

## Petani (Farmer) Endpoints

### 1. Upload Detection (Pest Detection)
- **Endpoint:** `POST /api/petani/detections`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Upload gambar hama dengan GPS location dan deskripsi
- **Request Body (form-data):**
  - `image` (file, required): image|mimes:jpeg,png,jpg,gif|max:5120 (5MB)
  - `description` (string, optional): Deskripsi tanaman/lokasi
  - `latitude` (numeric, optional): Koordinat latitude
  - `longitude` (numeric, optional): Koordinat longitude

- **Response (201):**
  ```json
  {
    "message": "Deteksi berhasil disimpan",
    "detection": {
      "id": "integer",
      "user_id": "integer",
      "image_path": "string",
      "detected_at": "timestamp",
      "status": "pending",
      "description": "string or null",
      "location": "string (lat,long) or null",
      "detectionResults": [
        {
          "id": "integer",
          "pest_name": "string",
          "confidence": "decimal (0-1)"
        }
      ],
      "recommendations": [
        {
          "id": "integer",
          "recommendation_text": "string",
          "source": "AI",
          "is_validated": false
        }
      ]
    }
  }
  ```

### 2. Get Detection Detail
- **Endpoint:** `GET /api/petani/detections/{id}`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Lihat detail deteksi tertentu (hanya milik petani yang login)
- **Response (200):**
  ```json
  {
    "id": "integer",
    "user_id": "integer",
    "image_path": "string",
    "detected_at": "timestamp",
    "status": "pending|completed",
    "description": "string or null",
    "location": "string or null",
    "detectionResults": [ detection_results_array ],
    "recommendations": [ recommendations_array ]
  }
  ```

### 3. Get History (Detection List)
- **Endpoint:** `GET /api/petani/history?status=pending`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Lihat riwayat deteksi dengan status filter
- **Query Parameters:**
  - `status` (optional): 'pending' atau 'completed' untuk filter
- **Response (200):**
  ```json
  {
    "total": "integer",
    "pending_count": "integer",
    "completed_count": "integer",
    "data": [
      {
        "id": "integer",
        "image_path": "string",
        "detected_at": "timestamp",
        "status": "pending|completed",
        "description": "string or null",
        "location": "string or null"
      }
    ]
  }
  ```

---

## Penyuluh (Extension Worker) Endpoints

### 1. Get List of Detections
- **Endpoint:** `GET /api/penyuluh/detections?status=pending`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Lihat laporan deteksi dari petani di desa yang ditangani penyuluh
- **Query Parameters:**
  - `status` (optional): 'pending' atau 'completed' untuk filter
- **Response (200):**
  ```json
  {
    "total": "integer",
    "pending_count": "integer",
    "completed_count": "integer",
    "data": [
      {
        "id": "integer",
        "user_id": "integer",
        "image_path": "string",
        "detected_at": "timestamp",
        "status": "pending|completed",
        "user": {
          "id": "integer",
          "name": "string",
          "village": { village_object }
        }
      }
    ]
  }
  ```

### 2. Get Detection Detail
- **Endpoint:** `GET /api/penyuluh/detections/{id}`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Lihat detail deteksi termasuk hasil AI dan rekomendasi
- **Response (200):**
  ```json
  {
    "id": "integer",
    "user_id": "integer",
    "image_path": "string",
    "detected_at": "timestamp",
    "status": "pending|completed",
    "description": "string or null",
    "location": "string or null",
    "user": {
      "id": "integer",
      "name": "string",
      "no_hp": "string",
      "village": { village_object }
    },
    "detectionResults": [
      {
        "id": "integer",
        "pest_name": "string",
        "confidence": "decimal"
      }
    ],
    "recommendations": [
      {
        "id": "integer",
        "recommendation_text": "string",
        "source": "AI|penyuluh",
        "is_validated": "boolean",
        "createdBy": { user_object } or null
      }
    ]
  }
  ```

### 3. Submit Recommendation
- **Endpoint:** `POST /api/penyuluh/detections/{id}/recommendations`
- **Auth Required:** Yes (auth:sanctum)
- **Description:** Submit rekomendasi untuk deteksi (mengubah status menjadi 'completed')
- **Request Body:**
  ```json
  {
    "recommendation_text": "string (required)"
  }
  ```
- **Response (201):**
  ```json
  {
    "message": "Rekomendasi berhasil disimpan",
    "recommendation": {
      "id": "integer",
      "detection_id": "integer",
      "recommendation_text": "string",
      "source": "penyuluh",
      "created_by": "integer",
      "is_validated": false
    },
    "detection": {
      "id": "integer",
      "status": "completed"
    }
  }
  ```

---

## Admin Endpoints

All admin endpoints require `auth:sanctum` + `middleware:admin`

### Villages Management

#### 1. Get All Villages
- **Endpoint:** `GET /api/admin/villages`
- **Response (200):** Array of village objects

#### 2. Create Village
- **Endpoint:** `POST /api/admin/villages`
- **Request Body:**
  ```json
  {
    "village_name": "string (required, unique)",
    "district": "string (required)"
  }
  ```
- **Response (201):** Created village object

#### 3. Update Village
- **Endpoint:** `PUT /api/admin/villages/{id}`
- **Request Body:**
  ```json
  {
    "village_name": "string (optional)",
    "district": "string (optional)"
  }
  ```
- **Response (200):** Updated village object

#### 4. Delete Village
- **Endpoint:** `DELETE /api/admin/villages/{id}`
- **Response (200):** Success message

### Penyuluh Management

#### 1. Get All Penyuluh
- **Endpoint:** `GET /api/admin/penyuluh`
- **Response (200):** Array of penyuluh users dengan managedVillages

#### 2. Create Penyuluh
- **Endpoint:** `POST /api/admin/penyuluh`
- **Request Body:**
  ```json
  {
    "name": "string (required)",
    "username": "string (required, unique)",
    "email": "string (required, unique, email)",
    "password": "string (required, min:6)",
    "no_hp": "string (optional)",
    "villages": [1, 2, 3] (array of village IDs, required)
  }
  ```
- **Response (201):** Created penyuluh object dengan managedVillages

#### 3. Update Penyuluh
- **Endpoint:** `PUT /api/admin/penyuluh/{id}`
- **Request Body:**
  ```json
  {
    "name": "string (optional)",
    "username": "string (optional, unique)",
    "email": "string (optional, unique, email)",
    "password": "string (optional, min:6)",
    "no_hp": "string (optional)",
    "villages": [1, 2, 3] (array of village IDs, optional)
  }
  ```
- **Response (200):** Updated penyuluh object

#### 4. Delete Penyuluh
- **Endpoint:** `DELETE /api/admin/penyuluh/{id}`
- **Response (200):** Success message

---

## Error Responses

### 400 Bad Request
```json
{
  "message": "Validation failed",
  "errors": {
    "field_name": ["error message"]
  }
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "message": "Unauthorized"
}
```

### 404 Not Found
```json
{
  "message": "Not found"
}
```

### 422 Unprocessable Entity
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["error message"]
  }
}
```

---

## Sample User Credentials (After Seeding)

**Admin:**
- Username: superadmin@admin.com
- Password: password123

**Penyuluh (8 accounts):**
- Email: penyuluh1@penyuluh.com - penyuluh8@penyuluh.com
- Password: password123 (same for all)

---

## Database Schema Overview

- **users:** User accounts dengan role_id dan village_id
- **roles:** Admin, Penyuluh, Petani
- **villages:** Desa/Kelurahan yang ditangani
- **village_user:** Pivot table untuk many-to-many relationship penyuluh-villages
- **detections:** Laporan deteksi hama dari petani
- **detection_results:** Hasil AI detection (pest_name, confidence)
- **recommendations:** Rekomendasi dari AI atau penyuluh
- **personal_access_tokens:** Sanctum tokens untuk authentication
