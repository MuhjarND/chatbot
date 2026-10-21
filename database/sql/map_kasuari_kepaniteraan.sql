-- Jalankan di database CHATBOT setelah aplikasi 'kasuari' terdaftar.
-- Sumber: sys_users.sql, 21 Oktober 2026. Tidak mengubah data employees.
DROP TEMPORARY TABLE IF EXISTS tmp_kasuari_kepaniteraan;
CREATE TEMPORARY TABLE tmp_kasuari_kepaniteraan (
    fullname VARCHAR(255) NOT NULL,
    app_user_id VARCHAR(255) PRIMARY KEY,
    is_active TINYINT NOT NULL
);

INSERT INTO tmp_kasuari_kepaniteraan VALUES
('Missah Hamzah Suara, S.H.', '16', 0),
('Dr. Imran, S.Ag., S.H., M.H.', '17', 0),
('Khoiriyah, S.Ag., M.H.', '18', 1),
('Akram, S.H., M.H.', '19', 0),
('Musa Sholawat, S.H.I.', '20', 1),
('Baida Makasar, S.Ag.', '21', 1),
('Drs. Erwin Romel, M.H.', '22', 1);

INSERT INTO employee_app_accounts
    (employee_id, application_code, app_user_id,
     is_active, created_at, updated_at)
SELECT e.id, 'kasuari', m.app_user_id,
       CASE WHEN e.is_active = 1 THEN m.is_active ELSE 0 END,
       NOW(), NOW()
FROM tmp_kasuari_kepaniteraan AS m
JOIN employees AS e
    ON LOWER(TRIM(e.name)) = LOWER(TRIM(m.fullname))
WHERE (SELECT COUNT(*) FROM employees AS e2
       WHERE LOWER(TRIM(e2.name)) = LOWER(TRIM(m.fullname))) = 1
ON DUPLICATE KEY UPDATE
    app_user_id = VALUES(app_user_id),
    is_active = VALUES(is_active),
    updated_at = NOW();

-- Hasil ini harus kosong. Jika muncul, nama belum ada atau tidak unik.
SELECT m.fullname, m.app_user_id, COUNT(e.id) AS jumlah_pegawai
FROM tmp_kasuari_kepaniteraan AS m
LEFT JOIN employees AS e
    ON LOWER(TRIM(e.name)) = LOWER(TRIM(m.fullname))
GROUP BY m.fullname, m.app_user_id
HAVING COUNT(e.id) <> 1;

DROP TEMPORARY TABLE tmp_kasuari_kepaniteraan;
