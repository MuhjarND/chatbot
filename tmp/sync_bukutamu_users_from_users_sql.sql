START TRANSACTION;

SET @application_code := 'bukutamu';

INSERT INTO applications (code, name, base_url, is_active, created_at, updated_at)
VALUES (@application_code, 'Buku Tamu', 'https://bukutamu.pta-papuabarat.go.id', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    base_url = VALUES(base_url),
    is_active = VALUES(is_active),
    updated_at = NOW();

CREATE TEMPORARY TABLE tmp_chatbot_bukutamu_import (
    app_user_id VARCHAR(191) NOT NULL,
    name VARCHAR(255) NOT NULL,
    nip VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    whatsapp_number VARCHAR(30) NOT NULL,
    mapping_is_active TINYINT(1) NOT NULL,
    PRIMARY KEY (app_user_id),
    UNIQUE KEY tmp_chatbot_bukutamu_import_whatsapp_unique (whatsapp_number)
);

INSERT INTO tmp_chatbot_bukutamu_import
    (app_user_id, name, nip, email, whatsapp_number, mapping_is_active)
VALUES
    ('4', 'Dr. Drs. H. Moh. Faishol Hasanuddin, S.H., M.H.', NULL, 'moh.faishol.hasanuddin@ptapapuabarat.com', '6281232567799', 1),
    ('5', 'Drs. Muhammad Takdir, S.H., M.H.', NULL, 'muhammad.takdir@ptapapuabarat.com', '6285242703739', 1),
    ('6', 'Drs Basyirun, M.H.', NULL, 'basyirun@ptapapuabarat.com', '6281230804025', 1),
    ('7', 'Drs. Rahmat Farid, M.H.', NULL, 'rahmat.farid@ptapapuabarat.com', '6281345218327', 1),
    ('8', 'Nurmansyah, S.Ag., M.H', NULL, 'nurmansyah@ptapapuabarat.com', '6281222138949', 0),
    ('9', 'Dr. Imran, S.Ag., S.H., M.H.', NULL, 'imran@ptapapuabarat.com', '6281344027540', 1),
    ('10', 'Khoiriyah, S.Ag., M.H.', NULL, 'khoiriyah@ptapapuabarat.com', '6281248659233', 1),
    ('11', 'Musa Sholawat, S.H.I.', NULL, 'musa.sholawat@ptapapuabarat.com', '6281248375717', 1),
    ('12', 'Raswin, S.H.I.', NULL, 'raswin@ptapapuabarat.com', '628114904111', 1),
    ('13', 'Syamsul Bahri, S.H.I.', NULL, 'syamsul.bahri@ptapapuabarat.com', '6282141677177', 1),
    ('14', 'Nasir Maswatu, S.H.I.', NULL, 'nasir.maswatu@ptapapuabarat.com', '6282239899026', 0),
    ('15', 'Akram, S.H., M.H.', NULL, 'akram@ptapapuabarat.com', '6282258729884', 1),
    ('16', 'Zubaidah Hi Hamzah, S.H.', NULL, 'zubaidah.hi.hamzah@ptapapuabarat.com', '6282198590925', 1),
    ('17', 'Missah Hamzah Suara, S.H.', NULL, 'missah.hamzah.suara@ptapapuabarat.com', '6281247288998', 1),
    ('18', 'Manik Rochmani, S.H.', NULL, 'manik.rochmani@ptapapuabarat.com', '6282238794290', 1),
    ('19', 'Ummu Mukhlisa, S.H., M.H.', NULL, 'ummu.mukhlisa@ptapapuabarat.com', '6285254101684', 1),
    ('20', 'Suria Kencana, S.E.', NULL, 'suria.kencana@ptapapuabarat.com', '628114801296', 1),
    ('21', 'Muslim Amin, A.Md.A.B.', NULL, 'muslim.amin@ptapapuabarat.com', '6281222511152', 1),
    ('22', 'Sitti Suriyani Tuahuns, S.Pd.SD', NULL, 'sitti.suriyani.tuahuns@ptapapuabarat.com', '6285241057079', 1),
    ('23', 'Ahmad Nur Fajri, S.H.', NULL, 'ahmad.nur.fajri@ptapapuabarat.com', '6282333106343', 1),
    ('24', 'Akbar, S.H.', NULL, 'akbar@ptapapuabarat.com', '6282199187860', 1),
    ('25', 'MUHAMMAD LUTFI KHAKIM, A.Md.Kom.', NULL, 'muhammad.lutfi.khakim@ptapapuabarat.com', '6281215797615', 1),
    ('26', 'GILANG ARIEF MAULANA, S.E.', NULL, 'gilang.arief.maulana@ptapapuabarat.com', '6283129031844', 1),
    ('27', 'MUHJAR NIAS DANI, S.Kom.', NULL, 'muhjar.nias.dani@ptapapuabarat.com', '6281240170314', 1),
    ('28', 'YUDHIS SALVANIA PRADANA, S.I.Kom.', NULL, 'yudhis.salvania.pradana@ptapapuabarat.com', '6289617013769', 1),
    ('29', 'SAID SALASA', '198105142025211039', 'said.salasa@ptapapuabarat.com', '6281344597651', 1),
    ('30', 'IKSAN OHORELLA', '198301032025211030', 'iksan.ohorella@ptapapuabarat.com', '6281240772222', 1),
    ('31', 'HAMZAH SYAM', '199101042025211033', 'hamzah.syam@ptapapuabarat.com', '6281247784532', 1),
    ('32', 'YATINO', '199305112025211040', 'yatino@ptapapuabarat.com', '6282199439393', 1),
    ('33', 'Drs Pandi, S.H., M.H', NULL, 'pandi@ptapapuabarat.com', '62811551456', 0);

INSERT INTO employees (name, nip, email, whatsapp_number, role, is_active, created_at, updated_at)
SELECT name, nip, email, whatsapp_number, 'pegawai', 1, NOW(), NOW()
FROM tmp_chatbot_bukutamu_import
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    nip = COALESCE(NULLIF(VALUES(nip), ''), employees.nip),
    email = COALESCE(NULLIF(VALUES(email), ''), employees.email),
    role = COALESCE(NULLIF(employees.role, ''), 'pegawai'),
    is_active = 1,
    updated_at = NOW();

DELETE employee_app_accounts
FROM employee_app_accounts
INNER JOIN tmp_chatbot_bukutamu_import
    ON employee_app_accounts.application_code = @application_code
    AND employee_app_accounts.app_user_id = tmp_chatbot_bukutamu_import.app_user_id
INNER JOIN employees
    ON employees.whatsapp_number = tmp_chatbot_bukutamu_import.whatsapp_number
WHERE employee_app_accounts.employee_id <> employees.id;

INSERT INTO employee_app_accounts (employee_id, application_code, app_user_id, is_active, created_at, updated_at)
SELECT employees.id, @application_code, tmp_chatbot_bukutamu_import.app_user_id, tmp_chatbot_bukutamu_import.mapping_is_active, NOW(), NOW()
FROM tmp_chatbot_bukutamu_import
INNER JOIN employees
    ON employees.whatsapp_number = tmp_chatbot_bukutamu_import.whatsapp_number
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
    tmp_chatbot_bukutamu_import.app_user_id,
    tmp_chatbot_bukutamu_import.name,
    employees.id AS employee_id,
    employees.whatsapp_number,
    employee_app_accounts.is_active
FROM tmp_chatbot_bukutamu_import
INNER JOIN employees
    ON employees.whatsapp_number = tmp_chatbot_bukutamu_import.whatsapp_number
LEFT JOIN employee_app_accounts
    ON employee_app_accounts.employee_id = employees.id
    AND employee_app_accounts.application_code = @application_code
ORDER BY CAST(tmp_chatbot_bukutamu_import.app_user_id AS UNSIGNED);

DROP TEMPORARY TABLE tmp_chatbot_bukutamu_import;

COMMIT;
