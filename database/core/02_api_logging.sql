-- ---------------------------------------------------------------------------
-- API kérés-log + rate limit. A tábla önmagát tisztítja (lásd
-- ApiRequestLogModel::purgeOlderThan(), amit az ApiController::authenticate()
-- hív meg alkalmanként), nincs szükség külön cron feladatra.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_request_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  api_user_id INT UNSIGNED NULL,
  method VARCHAR(10) NOT NULL,
  path VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  status_code SMALLINT UNSIGNED NULL,
  duration_ms INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_user_created (api_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
