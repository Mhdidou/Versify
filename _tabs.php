<?php
$tab_actif = $tab_actif ?? '';
$tid = (int) $id_tournoi;

$tabs = [
    'overview'     => ['label' => 'Overview',     'url' => "tournoi_overview.php?id_tournoi=$tid"],
    'bracket'      => ['label' => 'Bracket',      'url' => "bracket_live.php?id_tournoi=$tid"],
    'participants' => ['label' => 'Participants', 'url' => "participants_apres_lancement.php?id_tournoi=$tid"],
    'standings'    => ['label' => 'Standings',    'url' => "classement.php?id_tournoi=$tid"],
    'rules'        => ['label' => 'Rules',        'url' => "regles.php?id_tournoi=$tid"],
];
?>
<div class="border-b border-slate-800 bg-slate-950/60 backdrop-blur-sm">
    <nav class="max-w-[95%] mx-auto px-6 flex items-center gap-1 overflow-x-auto">
        <?php foreach ($tabs as $key => $tab):
            $est_actif = ($tab_actif === $key);
        ?>
        <a href="<?= $tab['url'] ?>"
           class="relative px-4 py-3.5 text-xs font-bold uppercase tracking-widest whitespace-nowrap transition
                  <?= $est_actif ? 'text-indigo-400' : 'text-slate-400 hover:text-slate-200' ?>">
            <?= $tab['label'] ?>
            <?php if ($est_actif): ?>
            <span class="absolute left-3 right-3 -bottom-px h-0.5 bg-indigo-500 rounded-full"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>
