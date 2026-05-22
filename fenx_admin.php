<?php
// ============================================================
//  API ENDPOINTI ZA JAVNO REZERVACIJO (Brez potrebe po prijavi)
// ============================================================

// 1. Lovilec za pridobivanje zasedenih ur (GET zahtevek)
if (isset($_GET["api_action"]) && $_GET["api_action"] === "get_zasedene") {
    header("Content-Type: application/json");

    $host = getenv("DB_HOST") ?: "localhost";
    $name = getenv("DB_NAME") ?: "fenx";
    $user = getenv("DB_USER") ?: "root";
    $pass = getenv("DB_PASS") ?: "";

    try {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $datum = $_GET["datum"] ?? "";
        $stmt = $pdo->prepare("SELECT ura FROM termini WHERE datum = ?");
        $stmt->execute([$datum]);
        $ure = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // POPRAVLJENO: Tukaj je bila napaka json_json_encode
        echo json_encode(["zasedeneUre" => $ure]);
    } catch (Exception $e) {
        echo json_encode(["zasedeneUre" => [], "error" => $e->getMessage()]);
    }
    exit;
}

// 2. Lovilec za oddajo novega termina (POST zahtevek)
$inputData = json_decode(file_get_contents("php://input"), true);
if ($inputData && isset($inputData["api_action"]) && $inputData["api_action"] === "add_termin") {
    header("Content-Type: application/json");

    $host = getenv("DB_HOST") ?: "localhost";
    $name = getenv("DB_NAME") ?: "fenx";
    $user = getenv("DB_USER") ?: "root";
    $pass = getenv("DB_PASS") ?: "";

    try {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Preverimo, če je termin medtem že kdo zasedel
        $chk = $pdo->prepare("SELECT COUNT(*) FROM termini WHERE datum = ? AND ura = ?");
        $chk->execute([$inputData["datum"], $inputData["ura"]]);
        if ($chk->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(["status" => "error", "error" => "conflict"]);
            exit;
        }

        // Vstavimo nov termin
        $stmt = $pdo->prepare("INSERT INTO termini (ime, spol, storitev, opomba, datum, ura) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $inputData["ime"],
            $inputData["spol"],
            $inputData["storitev"],
            $inputData["opomba"],
            $inputData["datum"],
            $inputData["ura"]
        ]);

        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}


// ============================================================
//  FEN-X Frizerski Salon -- Admin Panel (PHP + MySQL + Login)
//  Zahteve: PHP 8+, PDO razširitev
// ============================================================

// Zaženemo sejo za sledenje prijave
session_start();

// ---- Nastavitve za prijavo ----
define("ADMIN_USER", "admin");
define("ADMIN_PASS", "admin");

// ---- Logika za odjavo ----
if (isset($_GET["action"]) && $_GET["action"] === "logout") {
    unset($_SESSION["admin_logged_in"]);
    session_destroy();
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}

// ---- Logika za prijavo ----
$login_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION["admin_logged_in"] = true;
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    } else {
        $login_error = "Napačno uporabniško ime ali geslo!";
    }
}

// ---- Preverjanje, če je uporabnik prijavljen ----
$is_logged_in = isset($_SESSION["admin_logged_in"]) && $_SESSION["admin_logged_in"] === true;

// Če uporabnik NI prijavljen, prikažemo samo prijavno okno in prekinemo izvajanje ostale kode
if (!$is_logged_in): ?>
    <!DOCTYPE html>
    <html lang="sl">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>FEN-X Admin Prijava</title>
        <link rel="stylesheet" href="fenx_styles.css" />
        <style>
            body { background: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Roboto', sans-serif; margin: 0; }
            .login-box { background: #fff; padding: 2.5rem; width: 100%; max-width: 400px; border-top: 5px solid #C9A24D; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
            .login-box h2 { font-size: 2.5rem; margin-bottom: 1.5rem; color: #000; letter-spacing: 0.05em; }
            .form-group { margin-bottom: 1.25rem; text-align: left; }
            .form-group label { display: block; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; font-weight: bold; color: #555; }
            .form-group input { width: 100%; padding: 0.75rem; border: 2px solid #000; font-size: 1rem; box-sizing: border-box; }
            .btn-login { background: #000; color: #C9A24D; border: none; width: 100%; padding: 0.75rem; font-family: 'Bebas Neue', sans-serif; font-size: 1.25rem; letter-spacing: 0.1em; cursor: pointer; transition: background 0.2s; margin-top: 0.5rem; }
            .btn-login:hover { background: #222; }
            .error-msg { color: #dc2626; font-size: 0.85rem; margin-bottom: 1rem; text-align: left; font-weight: bold; }
            .back-home { display: inline-block; margin-top: 1.5rem; color: #6b7280; text-decoration: none; font-size: 0.85rem; }
            .back-home:hover { color: #000; }
        </style>
    </head>
    <body>
    <div class="login-box">
        <h2>FEN-X PRIJAVA</h2>
        <?php if (!empty($login_error)): ?>
            <div class="error-msg">⚠ <?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="login" value="1" />
            <div class="form-group">
                <label>Uporabniško ime</label>
                <input type="text" name="username" required autocomplete="off" />
            </div>
            <div class="form-group">
                <label>Geslo</label>
                <input type="password" name="password" required />
            </div>
            <button class="btn-login" type="submit">PRIJAVI SE</button>
        </form>
        <a class="back-home" href="index.html">← Nazaj na začetno stran</a>
    </div>
    </body>
    </html>
    <?php
    exit; // Zaustavimo skripto, da se spodnja vsebina admin panela ne naloži nepooblaščenim
endif;

// ============================================================
// OD TU NAPREJ SE KODA IZVEDE LE, ČE JE PRIJAVA USPEŠNA
// ============================================================

// ---- Konfiguracija baze podatkov ----
define("DB_HOST", getenv("DB_HOST") ?: "localhost");
define("DB_NAME", getenv("DB_NAME") ?: "fenx");
define("DB_USER", getenv("DB_USER") ?: "root");
define("DB_PASS", getenv("DB_PASS") ?: "");

// ---- Vzpostavitev povezave ----
function getDb(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ---- Brisanje termina ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {
    $id = (int)$_POST["delete_id"];
    getDb()->prepare("DELETE FROM termini WHERE id = ?")->execute([$id]);
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}

// ---- Filtriranje po datumu ----
$filterDate = $_GET["datum"] ?? null;
$today      = date("Y-m-d");

// ---- Statistika ----
$db = getDb();

$statsStmt = $db->query("
    SELECT
        SUM(IF(datum = '" . $today . "', 1, 0)) AS danes,
        SUM(IF(STR_TO_DATE(datum, '%Y-%m-%d') >= DATE_SUB('" . $today . "', INTERVAL WEEKDAY('" . $today . "') DAY), 1, 0)) AS ta_teden,
        COUNT(*) AS skupaj
    FROM termini
");
$stats = $statsStmt->fetch();

// ---- Termini ----
if ($filterDate) {
    $stmt = $db->prepare("SELECT * FROM termini WHERE datum = ? ORDER BY ura ASC");
    $stmt->execute([$filterDate]);
} else {
    $stmt = $db->query("SELECT * FROM termini ORDER BY datum DESC, ura ASC");
}
$termini = $stmt->fetchAll();

// ---- Skupaj po storitvah ----
$storitveStmt = $db->query("SELECT storitev, COUNT(*) AS stevilo FROM termini GROUP BY storitev ORDER BY stevilo DESC");
$storitve     = $storitveStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FEN-X Admin Panel</title>
    <link rel="stylesheet" href="fenx_styles.css" />
    <style>
        body { background: #f9fafb; }
        .admin-wrap { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 2rem 0; }
        .stat-card { background: #fff; border: 1px solid #e5e7eb; padding: 1.5rem; text-align: center; }
        .stat-num { font-family: 'Bebas Neue', sans-serif; font-size: 3rem; color: #dc2626; display: block; }
        .stat-label { color: #6b7280; font-size: 0.85rem; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; }
        th { background: #000; color: #f3f4f6; font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.1em; font-size: 1rem; }
        tr:hover td { background: #f9fafb; }
        .btn-delete {
            background: none; border: 1px solid #fca5a5;
            color: #dc2626; padding: 0.25rem 0.75rem;
            cursor: pointer; font-size: 0.8rem;
            transition: background 0.15s;
        }
        .btn-delete:hover { background: #fee2e2; }
        .filter-form { display: flex; gap: 1rem; align-items: center; margin: 1.5rem 0; }
        .filter-form input[type=date] { border: 2px solid #000; padding: 0.5rem 1rem; font-size: 0.9rem; }
        .filter-form button { background: #000; color: #fff; border: none; padding: 0.5rem 1.5rem; cursor: pointer; font-family: 'Bebas Neue', sans-serif; font-size: 1rem; }
        .filter-form a { color: #6b7280; font-size: 0.85rem; text-decoration: none; }
        .storitve-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0 2rem; }
        .storitev-badge { background: #000; color: #dc2626; padding: 0.4rem 1rem; font-family: 'Bebas Neue', sans-serif; font-size: 0.9rem; }
        .back-link { display: inline-block; color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .back-link:hover { color: #dc2626; }
        .btn-logout { background: #dc2626; color: #fff; text-decoration: none; padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 3px; font-weight: bold; transition: background 0.15s; }
        .btn-logout:hover { background: #b91c1c; }
    </style>
</head>
<body>

<header style="background:#000;padding:1rem 2rem;display:flex;align-items:center;justify-content:between;justify-content: space-between;">
    <div style="display:flex;align-items:center;gap:1rem;">
        <span style="font-family:'Bebas Neue',sans-serif;font-size:2.5rem;color:#dc2626;">FEN-X</span>
        <span style="color:#fff;font-size:0.85rem;opacity:0.6;text-transform:uppercase;letter-spacing:0.1em;">Admin Panel</span>
    </div>
    <a class="btn-logout" href="?action=logout">Odjava</a>
</header>

<div class="admin-wrap">

    <a class="back-link" href="index.html">← Nazaj na stran</a>

    <h2 style="font-size:2.5rem;margin: 2rem 0 0.5rem 0;">STATISTIKA</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-num"><?= (int)($stats["danes"] ?? 0) ?></span>
            <span class="stat-label">Danes</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= (int)($stats["ta_teden"] ?? 0) ?></span>
            <span class="stat-label">Ta teden</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= (int)($stats["skupaj"] ?? 0) ?></span>
            <span class="stat-label">Skupaj</span>
        </div>
    </div>

    <h3 style="font-size:1.5rem;margin-bottom:0.5rem;">PO STORITVAH</h3>
    <div class="storitve-list">
        <?php foreach ($storitve as $s): ?>
            <div class="storitev-badge"><?= htmlspecialchars($s["storitev"]) ?> (<?= $s["stevilo"] ?>)</div>
        <?php endforeach; ?>
        <?php if (empty($storitve)): ?><p style="color:#9ca3af;">Ni podatkov.</p><?php endif; ?>
    </div>

    <h2 style="font-size:2.5rem;margin-bottom:0;">TERMINI</h2>
    <form class="filter-form" method="GET">
        <input type="date" name="datum" value="<?= htmlspecialchars($filterDate ?? "") ?>" />
        <button type="submit">FILTRIRAJ</button>
        <?php if ($filterDate): ?>
            <a href="<?= $_SERVER["PHP_SELF"] ?>">Prikaži vse</a>
        <?php endif; ?>
    </form>

    <?php if (empty($termini)): ?>
        <p style="color:#9ca3af;padding:2rem 0;">Ni terminov<?= $filterDate ? " za ta dan." : "." ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>IME</th>
                <th>SPOL</th>
                <th>STORITEV</th>
                <th>DATUM</th>
                <th>URA</th>
                <th>OPOMBA</th>
                <th>AKCIJA</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($termini as $t): ?>
                <tr>
                    <td><?= (int)$t["id"] ?></td>
                    <td><strong><?= htmlspecialchars($t["ime"]) ?></strong></td>
                    <td><?= htmlspecialchars($t["spol"]) ?></td>
                    <td><?= htmlspecialchars($t["storitev"]) ?></td>
                    <td><?= htmlspecialchars($t["datum"]) ?></td>
                    <td><?= htmlspecialchars($t["ura"]) ?></td>
                    <td><?= htmlspecialchars($t["opomba"] ?? "—") ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Ste prepričani, da želite izbrisati ta termin?')">
                            <input type="hidden" name="delete_id" value="<?= (int)$t["id"] ?>" />
                            <button class="btn-delete" type="submit">&#128465; Izbriši</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

</body>
</html>