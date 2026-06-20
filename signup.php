<?php
$success = null;
$erreur  = null;

if (isset($_POST['id_utilisateur'], $_POST['email'], $_POST['pays'], $_POST['date_de_naissance'], $_POST['mdp'])
    && !empty($_POST['id_utilisateur']) && !empty($_POST['email']) && !empty($_POST['pays'])
    && !empty($_POST['date_de_naissance']) && !empty($_POST['mdp'])) {
    try {
        require_once __DIR__ . '/config.php';

        $pdostat = $pdo->prepare("INSERT INTO utilisateur (id_utilisateur, email, pays, date_de_naissance, mdp) VALUES (:id_utilisateur, :email, :pays, :date_de_naissance, :mdp)");
        $pdostat->execute([
            ":id_utilisateur"     => $_POST['id_utilisateur'],
            ":email"              => $_POST['email'],
            ":pays"               => $_POST['pays'],
            ":date_de_naissance"  => $_POST['date_de_naissance'],
            ":mdp"                => password_hash($_POST['mdp'], PASSWORD_DEFAULT)
        ]);

        $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
    } catch (PDOException $e) {
        $erreur = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
        .grid-bg {
            background-image:
                linear-gradient(rgba(99,102,241,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.07) 1px, transparent 1px);
            background-size: 36px 36px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <a href="login.php" class="text-sm font-medium text-slate-400 hover:text-white transition">← Déjà un compte ? Log In</a>
        </div>
    </nav>

    <section class="relative min-h-[calc(100vh-4rem)] overflow-hidden">
        <div class="absolute inset-0 grid-bg pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-600/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="relative z-10 flex items-center justify-center py-16 px-6">
            <div class="glass-card p-8 md:p-10 border border-slate-800 rounded-2xl w-full max-w-2xl shadow-2xl hover:border-indigo-500/40 transition-all duration-300">
                <div class="mb-8 text-center">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[10px] font-bold uppercase tracking-widest mb-4">
                        <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full"></span>
                        Inscription gratuite
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight mb-2">Création du compte</h2>
                    <p class="text-sm text-slate-400">Rejoignez la plateforme et lancez votre premier tournoi en quelques minutes.</p>
                </div>

                <?php if ($success): ?>
                <div class="mb-5 flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 p-3.5 rounded text-sm">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="font-medium"><?= htmlspecialchars($success) ?> <a href="login.php" class="underline font-bold">Se connecter →</a></span>
                </div>
                <?php endif; ?>

                <?php if ($erreur): ?>
                <div class="mb-5 flex items-start gap-3 bg-red-500/10 border border-red-500/40 text-red-400 p-3.5 rounded text-sm">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span class="font-medium"><?= htmlspecialchars($erreur) ?></span>
                </div>
                <?php endif; ?>

                <form id="signup-form" action="signup.php" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Nom d'utilisateur</label>
                        <input type="text" name="id_utilisateur" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition" required>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Adresse email</label>
                        <input type="email" name="email" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition" required>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Pays</label>
                        <select name="pays" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition dark:[color-scheme:dark]" required>
                            <option value="" disabled selected>Sélectionnez un pays</option>
                            <option value="Afghanistan">Afghanistan</option>
                            <option value="Albania">Albania</option>
                            <option value="Algeria">Algeria</option>
                            <option value="Argentina">Argentina</option>
                            <option value="Australia">Australia</option>
                            <option value="Austria">Austria</option>
                            <option value="Belgium">Belgium</option>
                            <option value="Brazil">Brazil</option>
                            <option value="Canada">Canada</option>
                            <option value="China">China</option>
                            <option value="Denmark">Denmark</option>
                            <option value="Egypt">Egypt</option>
                            <option value="Finland">Finland</option>
                            <option value="France">France</option>
                            <option value="Germany">Germany</option>
                            <option value="Greece">Greece</option>
                            <option value="India">India</option>
                            <option value="Indonesia">Indonesia</option>
                            <option value="Ireland">Ireland</option>
                            <option value="Italy">Italy</option>
                            <option value="Japan">Japan</option>
                            <option value="Mexico">Mexico</option>
                            <option value="Morocco">Morocco</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="New Zealand">New Zealand</option>
                            <option value="Norway">Norway</option>
                            <option value="Pakistan">Pakistan</option>
                            <option value="Philippines">Philippines</option>
                            <option value="Poland">Poland</option>
                            <option value="Portugal">Portugal</option>
                            <option value="Romania">Romania</option>
                            <option value="Russian Federation">Russian Federation</option>
                            <option value="Saudi Arabia">Saudi Arabia</option>
                            <option value="Senegal">Senegal</option>
                            <option value="Singapore">Singapore</option>
                            <option value="South Africa">South Africa</option>
                            <option value="Korea, Republic of">South Korea</option>
                            <option value="Spain">Spain</option>
                            <option value="Sweden">Sweden</option>
                            <option value="Switzerland">Switzerland</option>
                            <option value="Tunisia">Tunisia</option>
                            <option value="Turkey">Turkey</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="Viet Nam">Vietnam</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Date de naissance</label>
                        <input type="date" name="date_de_naissance" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition dark:[color-scheme:dark]" required>
                    </div>

                    <div class="col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Mot de passe</label>
                        <input id="password" type="password" name="mdp" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition" required>
                        <!-- Password strength meter -->
                        <div class="mt-2 flex gap-1">
                            <div id="bar-1" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors"></div>
                            <div id="bar-2" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors"></div>
                            <div id="bar-3" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors"></div>
                            <div id="bar-4" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors"></div>
                        </div>
                        <p id="strength-label" class="text-[11px] text-slate-500 mt-1.5 h-4">Entrez un mot de passe.</p>
                    </div>

                    <div class="col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Confirmation du mot de passe</label>
                        <input id="confirm-password" type="password" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition" required>
                        <p id="password-error" class="text-red-500 text-xs font-bold mt-2 hidden transition-all">La confirmation ne correspond pas.</p>
                    </div>

                    <div class="col-span-1 md:col-span-2 mt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 w-full uppercase tracking-widest">
                            Créer mon compte
                        </button>
                        <p class="text-center text-xs text-slate-500 mt-4">
                            En vous inscrivant, vous acceptez les conditions d'utilisation de la plateforme.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('signup-form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm-password');
    const errorMessage = document.getElementById('password-error');
    const bars = [1,2,3,4].map(i => document.getElementById('bar-'+i));
    const strengthLabel = document.getElementById('strength-label');

    const colors = ['bg-red-500', 'bg-orange-500', 'bg-amber-500', 'bg-emerald-500'];
    const labels = ['Très faible', 'Faible', 'Correct', 'Fort'];

    function strength(pw) {
        let s = 0;
        if (pw.length >= 6) s++;
        if (pw.length >= 10) s++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
        if (/\d/.test(pw) && /[^A-Za-z0-9]/.test(pw)) s++;
        return s;
    }

    function updateStrength() {
        const s = password.value ? strength(password.value) : 0;
        bars.forEach((b, i) => {
            b.className = 'h-1 flex-1 rounded-full transition-colors ' + (i < s ? colors[s-1] : 'bg-slate-800');
        });
        strengthLabel.textContent = password.value ? labels[Math.max(0, s-1)] : 'Entrez un mot de passe.';
        strengthLabel.className = 'text-[11px] mt-1.5 h-4 ' + (password.value ? ['text-red-400','text-orange-400','text-amber-400','text-emerald-400'][Math.max(0,s-1)] : 'text-slate-500');
    }

    function validatePasswordMatch() {
        if (confirmPassword.value === '') {
            errorMessage.classList.add('hidden');
            confirmPassword.classList.remove('border-red-500', 'border-emerald-500');
            confirmPassword.classList.add('border-slate-700');
            return;
        }
        if (password.value === confirmPassword.value) {
            errorMessage.classList.add('hidden');
            confirmPassword.classList.remove('border-red-500', 'border-slate-700');
            confirmPassword.classList.add('border-emerald-500');
        } else {
            confirmPassword.classList.remove('border-emerald-500', 'border-slate-700');
            confirmPassword.classList.add('border-red-500');
        }
    }

    password.addEventListener('input', () => { updateStrength(); validatePasswordMatch(); });
    confirmPassword.addEventListener('input', validatePasswordMatch);

    form.addEventListener('submit', function(event) {
        if (password.value !== confirmPassword.value) {
            event.preventDefault();
            errorMessage.classList.remove('hidden');
            validatePasswordMatch();
        }
    });
});
</script>
<?php include '_theme.php'; ?>
</body>
</html>
