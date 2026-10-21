-- Jalankan seluruh script pada database CHATBOT hosting.
-- Tidak membuat/mengubah pegawai. NIP lima akun berasal dari employees.sql dan users.sql.
-- NIP Baida/Erwin: Rekap Data Pegawai (1).xlsx dari sumber project Kerjaan (baris 27/18).
-- Mapping lama yang nonaktif tidak akan diaktifkan kembali.
DROP TEMPORARY TABLE IF EXISTS tmp_kasuari_all;
CREATE TEMPORARY TABLE tmp_kasuari_all (
    app_user_id VARCHAR(255) PRIMARY KEY,
    nip VARCHAR(18) NOT NULL,
    nama VARCHAR(255) NOT NULL,
    is_active TINYINT NOT NULL
);

INSERT INTO tmp_kasuari_all VALUES
('1', '200009222025061012', 'Administrator', 1),
('2', '196308081989031005', 'Drs. Muhammad Takdir, S.H., M.H.', 1),
('3', '196308161994031001', 'Drs. Basyirun, M.H.', 1),
('4', '196402051992031005', 'Dr Acep Saifuddin, S.H., M.Ag.', 1),
('5', '196406121992021001', 'Drs. Syafrudin Mohamad, M.H.', 1),
('6', '196510051991031008', 'Drs. Rahmat Farid, M.H.', 1),
('7', '196604141994031006', 'Drs. Mahzumi, M.H.', 1),
('8', '196604301992031001', 'Dr. Drs Muhlas, S.H., M.H.', 1),
('9', '196605291994021001', 'Drs Ihsan, M.H.', 1),
('10', '196707151993031005', 'Drs. Komsun, S.H., M.H.E.S.', 1),
('11', '196709151993031003', 'Drs. Khotibul Umam', 1),
('12', '196711121993031003', 'Drs. Dindin Syarief Nurwahyudin', 1),
('13', '196712101992031001', 'Drs. H. Masnun, S.H.', 1),
('14', '196712311994031051', 'Drs. Syamsul Bahri, M.H.', 1),
('15', '196910091994031003', 'Drs. Muhammad Iskandar Eko Putro, M.H.', 1),
('16', '196704081989011001', 'Missah Hamzah Suara, S.H.', 0),
('17', '197108221996031001', 'Dr. Imran, S.Ag., S.H., M.H.', 0),
('18', '197504152000032002', 'Khoiriyah, S.Ag., M.H.', 1),
('19', '197908132000121003', 'Akram, S.H., M.H.', 0),
('20', '197908172002121004', 'Musa Sholawat, S.H.I.', 1),
('21', '197207292001122001', 'Baida Makasar, S.Ag.', 1),
('22', '196712151993031006', 'Drs. Erwin Romel, M.H.', 1);

INSERT INTO applications
    (code, name, base_url, is_active, created_at, updated_at)
VALUES ('kasuari', 'Kasuari', 'https://kasuari.pta-papuabarat.go.id', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    base_url = VALUES(base_url), is_active = 1, updated_at = NOW();

INSERT INTO employee_app_accounts
    (employee_id, application_code, app_user_id, is_active, created_at, updated_at)
SELECT MIN(e.id), 'kasuari', m.app_user_id,
       CASE WHEN MIN(e.is_active) = 1 THEN m.is_active ELSE 0 END, NOW(), NOW()
FROM tmp_kasuari_all AS m
JOIN employees AS e ON TRIM(e.nip) = m.nip
GROUP BY m.app_user_id, m.is_active
HAVING COUNT(e.id) = 1
ON DUPLICATE KEY UPDATE
    app_user_id = VALUES(app_user_id),
    is_active = LEAST(employee_app_accounts.is_active, VALUES(is_active)), updated_at = NOW();

-- Baris yang muncul dilewati: pegawai dengan NIP ini belum ada atau NIP terduplikasi.
SELECT m.app_user_id, m.nip, m.nama, COUNT(e.id) AS jumlah_pegawai
FROM tmp_kasuari_all AS m
LEFT JOIN employees AS e ON TRIM(e.nip) = m.nip
GROUP BY m.app_user_id, m.nip, m.nama
HAVING COUNT(e.id) <> 1;

DROP TEMPORARY TABLE tmp_kasuari_all;
