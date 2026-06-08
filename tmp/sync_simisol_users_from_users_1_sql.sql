START TRANSACTION;

SET @application_code := 'simisol';

INSERT INTO applications (code, name, base_url, is_active, created_at, updated_at)
VALUES (@application_code, 'Simisol', 'https://simisol.pta-papuabarat.go.id', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    base_url = VALUES(base_url),
    is_active = VALUES(is_active),
    updated_at = NOW();

CREATE TEMPORARY TABLE tmp_chatbot_simisol_import (
    app_user_id VARCHAR(191) NOT NULL,
    name VARCHAR(255) NOT NULL,
    nip VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    whatsapp_number VARCHAR(30) NOT NULL,
    mapping_is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (app_user_id),
    UNIQUE KEY tmp_chatbot_simisol_import_whatsapp_unique (whatsapp_number)
);

INSERT INTO tmp_chatbot_simisol_import
    (app_user_id, name, nip, email, whatsapp_number, mapping_is_active)
VALUES
    ('2', 'Nur Komariah, S.Si', NULL, 'nurkomariah@pta-papuabarat.go.id', '6282239292070', 1),
    ('57', 'Ravid Bahar, S.H', NULL, 'ravidbahar@pta-papuabarat.go.id', '6281226130308', 1),
    ('58', 'Iksan Ohorella', '198301032025211030', 'iksanohorella@pta-papuabarat.go.id', '6281240772222', 1),
    ('59', 'Hamzah Syam', '199101042025211033', 'hamzahsyam@pta-papuabarat.go.id', '6281247784532', 1),
    ('62', 'Yatino', '199305112025211040', 'yatino@pta-papuabarat.go.id', '6282199439393', 1),
    ('63', 'Said Salasa', '198105142025211039', 'saidsalasa@pta-papuabarat.go.id', '6281344597651', 1),
    ('131', 'Dr. Imran, S.Ag., S.H., M.H.', '197108221996031001', 'imran@pta-papuabarat.go.id', '6281344027540', 1),
    ('142', 'Dr Acep Saifuddin, S.H., M.Ag.', '196402051992031005', 'acepsaifuddin@pta-papuabarat.go.id', '6281324500445', 1),
    ('143', 'Drs. Syafrudin Mohamad, M.H.', '196406121992021001', 'syafrudinmohamad@pta-papuabarat.go.id', '6285241592525', 1),
    ('144', 'Drs. Mahzumi, M.H.', '196604141994031006', 'mahzumi@pta-papuabarat.go.id', '6281259680466', 1),
    ('145', 'Drs Ihsan, M.H.', '196605291994021001', 'ihsan@pta-papuabarat.go.id', '6289628806116', 1),
    ('146', 'Drs. Komsun, S.H., M.H.E.S.', '196707151993031005', 'komsun@pta-papuabarat.go.id', '6281336684279', 1),
    ('147', 'Drs. Khotibul Umam', '196709151993031003', 'khotibulumam@pta-papuabarat.go.id', '6281329092709', 1),
    ('148', 'Drs. Dindin Syarief Nurwahyudin', '196711121993031003', 'dindinsyariefnurwahyudin@pta-papuabarat.go.id', '6281362172631', 1),
    ('149', 'Drs. H. Masnun, S.H.', '196712101992031001', 'masnun@pta-papuabarat.go.id', '6281313975997', 1),
    ('150', 'Drs. Syamsul Bahri, M.H.', '196712311994031051', 'syamsulbahri2@pta-papuabarat.go.id', '6285299452536', 1),
    ('151', 'Drs. Muhammad Iskandar Eko Putro, M.H.', '196910091994031003', 'muhammadiskandarekoputro@pta-papuabarat.go.id', '6281228242322', 1),
    ('152', 'Purnama Sari, S.Ag.', '197005171998032001', 'purnamasari@pta-papuabarat.go.id', '6285262130284', 1);

INSERT INTO employees (name, nip, email, whatsapp_number, role, is_active, created_at, updated_at)
SELECT name, nip, email, whatsapp_number, 'pegawai', 1, NOW(), NOW()
FROM tmp_chatbot_simisol_import
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    nip = COALESCE(NULLIF(VALUES(nip), ''), employees.nip),
    email = COALESCE(NULLIF(VALUES(email), ''), employees.email),
    role = COALESCE(NULLIF(employees.role, ''), 'pegawai'),
    is_active = 1,
    updated_at = NOW();

DELETE employee_app_accounts
FROM employee_app_accounts
INNER JOIN tmp_chatbot_simisol_import
    ON employee_app_accounts.application_code = @application_code
    AND employee_app_accounts.app_user_id = tmp_chatbot_simisol_import.app_user_id
INNER JOIN employees
    ON employees.whatsapp_number = tmp_chatbot_simisol_import.whatsapp_number
WHERE employee_app_accounts.employee_id <> employees.id;

INSERT INTO employee_app_accounts (employee_id, application_code, app_user_id, is_active, created_at, updated_at)
SELECT employees.id, @application_code, tmp_chatbot_simisol_import.app_user_id, tmp_chatbot_simisol_import.mapping_is_active, NOW(), NOW()
FROM tmp_chatbot_simisol_import
INNER JOIN employees
    ON employees.whatsapp_number = tmp_chatbot_simisol_import.whatsapp_number
ON DUPLICATE KEY UPDATE
    app_user_id = VALUES(app_user_id),
    is_active = VALUES(is_active),
    updated_at = NOW();

SELECT
    employee_app_accounts.application_code,
    COUNT(*) AS total_mapping,
    SUM(employee_app_accounts.is_active = 1) AS active_mapping
FROM employee_app_accounts
WHERE employee_app_accounts.application_code = @application_code
GROUP BY employee_app_accounts.application_code;

SELECT
    tmp_chatbot_simisol_import.app_user_id,
    tmp_chatbot_simisol_import.name,
    employees.id AS employee_id,
    employees.whatsapp_number,
    employee_app_accounts.is_active
FROM tmp_chatbot_simisol_import
INNER JOIN employees
    ON employees.whatsapp_number = tmp_chatbot_simisol_import.whatsapp_number
LEFT JOIN employee_app_accounts
    ON employee_app_accounts.employee_id = employees.id
    AND employee_app_accounts.application_code = @application_code
ORDER BY CAST(tmp_chatbot_simisol_import.app_user_id AS UNSIGNED);

DROP TEMPORARY TABLE tmp_chatbot_simisol_import;

COMMIT;
