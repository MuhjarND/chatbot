-- Jalankan pada database CHATBOT. Mapping pengguna yang ada tetap dipertahankan.
-- Perbarui URL Kasuari yang sudah terdaftar ke domain HTTPS.
INSERT INTO applications (code, name, base_url, is_active, created_at, updated_at)
VALUES ('kasuari', 'Kasuari', 'https://kasuari.pta-papuabarat.go.id', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    base_url = VALUES(base_url),
    is_active = 1,
    updated_at = NOW();

-- Periksa mapping yang tersedia tanpa mengubah ID pengguna Kasuari.
SELECT e.name, a.app_user_id, a.is_active
FROM employee_app_accounts AS a
JOIN employees AS e ON e.id = a.employee_id
WHERE LOWER(TRIM(a.application_code)) = 'kasuari'
ORDER BY e.name;
