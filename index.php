<?php
session_start();

class ProfileLoader
{
    private $configFile;

    public function __construct($configPath)
    {
        $this->configFile = $configPath;
    }

    public function load()
    {
        if (!file_exists($this->configFile)) {
            return [];
        }
        $content = file_get_contents($this->configFile);
        return json_decode($content, true) ?? [];
    }
}

class ProfilePresenter
{
    private $profileData;

    public function __construct(array $profileData)
    {
        $this->profileData = $profileData;
    }

    public function getName() { return htmlspecialchars($this->profileData['name'] ?? 'Profil'); }
    public function getBio() { return htmlspecialchars($this->profileData['bio'] ?? ''); }
    public function getSkills() { return $this->profileData['skills'] ?? []; }
    public function getInterests() { return $this->profileData['interests'] ?? []; }
    
    public function getProjects()
    {
        $projects = $this->profileData['projects'] ?? [];
        foreach ($projects as &$project) {
            $project['title'] = htmlspecialchars($project['title'] ?? 'Neznámý projekt');
            $project['description'] = htmlspecialchars($project['description'] ?? '');
        }
        return $projects;
    }
}

$loader = new ProfileLoader(__DIR__ . '/profile.json');
$profileData = $loader->load();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $updated = false;

    if ($action === 'add') {
        $newInterest = trim($_POST["new_interest"] ?? '');
        if (empty($newInterest)) {
            $_SESSION['msg'] = ["text" => "Pole nesmí být prázdné.", "type" => "error"];
        } else {
            $exists = false;
            foreach ($profileData['interests'] as $item) {
                if (mb_strtolower($item, 'UTF-8') === mb_strtolower($newInterest, 'UTF-8')) {
                    $exists = true; break;
                }
            }
            if ($exists) {
                $_SESSION['msg'] = ["text" => "Tento zájem už existuje.", "type" => "error"];
            } else {
                $profileData['interests'][] = $newInterest;
                $_SESSION['msg'] = ["text" => "Zájem byl úspěšně přidán.", "type" => "success"];
                $updated = true;
            }
        }
    }

    elseif ($action === 'delete') {
        $index = $_POST['index'] ?? -1;
        if (isset($profileData['interests'][$index])) {
            unset($profileData['interests'][$index]);
            $profileData['interests'] = array_values($profileData['interests']); // Reindexace pole
            $_SESSION['msg'] = ["text" => "Zájem byl odstraněn.", "type" => "success"];
            $updated = true;
        }
    }

    elseif ($action === 'edit') {
        $index = $_POST['index'] ?? -1;
        $newValue = trim($_POST['new_value'] ?? '');
        if (empty($newValue)) {
            $_SESSION['msg'] = ["text" => "Pole nesmí být prázdné.", "type" => "error"];
        } elseif (isset($profileData['interests'][$index])) {
            $exists = false;
            foreach ($profileData['interests'] as $idx => $item) {
                if ($idx != $index && mb_strtolower($item, 'UTF-8') === mb_strtolower($newValue, 'UTF-8')) {
                    $exists = true; break;
                }
            }
            if ($exists) {
                $_SESSION['msg'] = ["text" => "Tento zájem už existuje.", "type" => "error"];
            } else {
                $profileData['interests'][$index] = $newValue;
                $_SESSION['msg'] = ["text" => "Zájem byl upraven.", "type" => "success"];
                $updated = true;
            }
        }
    }

    if ($updated) {
        file_put_contents(__DIR__ . '/profile.json', json_encode($profileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    header("Location: index.php");
    exit;
}

$presenter = new ProfilePresenter($profileData);

$message = $_SESSION['msg'] ?? null;
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $presenter->getName(); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1><?php echo $presenter->getName(); ?></h1>
        
        <?php if (!empty($presenter->getBio())): ?>
            <p class="bio"><?php echo $presenter->getBio(); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($presenter->getSkills())): ?>
            <section class="skills-section">
                <h2>Dovednosti</h2>
                <ul class="skills-list">
                    <?php foreach ($presenter->getSkills() as $skill): ?>
                        <li><?php echo htmlspecialchars($skill); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        
        <?php if (!empty($presenter->getProjects())): ?>
            <section class="projects-section">
                <h2>Projekty</h2>
                <div class="projects-list">
                    <?php foreach ($presenter->getProjects() as $project): ?>
                        <div class="project-card">
                            <h3><?php echo $project['title']; ?></h3>
                            <p><?php echo $project['description']; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        
        <section class="interests-section">
            <h2>Zájmy</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message['type']; ?>" style="padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <ul class="interests-list">
                <?php foreach ($presenter->getInterests() as $index => $interest): ?>
                    <li style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span><?php echo htmlspecialchars($interest); ?></span>
                            <div style="display: flex; gap: 5px;">
                                <button type="button" onclick="document.getElementById('edit-form-<?php echo $index; ?>').style.display='flex'">Upravit</button>
                                
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" style="background: #991b1b;" onclick="return confirm('Opravdu smazat?')">Smazat</button>
                                </form>
                            </div>
                        </div>

                        <form id="edit-form-<?php echo $index; ?>" method="POST" style="display: none; gap: 5px; margin-top: 5px; background: #f3f4f6; padding: 5px; border-radius: 4px;">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <input type="text" name="new_value" value="<?php echo htmlspecialchars($interest); ?>" required style="flex: 1;">
                            <button type="submit">Uložit</button>
                            <button type="button" onclick="this.parentElement.style.display='none'">Zrušit</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <hr>
            
            <form method="POST" class="interest-form">
                <input type="hidden" name="action" value="add">
                <input type="text" name="new_interest" placeholder="Zadejte nový zájem..." required>
                <button type="submit">Přidat zájem</button>
            </form>
        </section>
    </div>
</body>
</html>