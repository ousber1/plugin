<?php
// Generates public/docs/guide-fichiers-ho-creative.pdf (original content).
require __DIR__.'/../vendor/autoload.php';
$tmp = sys_get_temp_dir().'/mpdf-guide';
@mkdir($tmp);
$m = new \Mpdf\Mpdf(['tempDir' => $tmp, 'default_font' => 'dejavusans', 'format' => 'A4']);
$m->SetTitle('Guide de préparation des fichiers – HO CREATIVE');
$m->WriteHTML('<style>h1{color:#E4007C}h2{color:#1B1F3B;border-bottom:1px solid #ddd}td{padding:4px;border:1px solid #ddd}</style>
<h1>Guide de préparation des fichiers</h1><p>HO CREATIVE – impression professionnelle au Maroc</p>
<h2>1. Format et fond perdu</h2><p>Créez votre document au format fini + <b>3 mm de fond perdu</b> de chaque côté (ex. carte 85 × 55 mm → document 91 × 61 mm). Gardez textes et logos à <b>4 mm minimum</b> de la ligne de coupe (zone de sécurité).</p>
<table><tr><td>Produit</td><td>Format fini</td><td>Document avec fond perdu</td></tr><tr><td>Carte de visite</td><td>85 × 55 mm</td><td>91 × 61 mm</td></tr><tr><td>Flyer A5</td><td>148 × 210 mm</td><td>154 × 216 mm</td></tr><tr><td>Flyer A4</td><td>210 × 297 mm</td><td>216 × 303 mm</td></tr></table>
<h2>2. Résolution</h2><p>Images à <b>300 dpi</b> à la taille d\'impression (150 dpi suffisent pour les bâches vues de loin). Une image web agrandie sera floue.</p>
<h2>3. Couleurs</h2><p>Travaillez en <b>CMJN</b>. Les fichiers RVB sont convertis par notre prépresse : les couleurs vives (bleus, verts fluo) peuvent légèrement changer. Noir de texte : 100 % K.</p>
<h2>4. Polices et format</h2><p>Exportez en <b>PDF</b> (idéalement PDF/X-1a ou PDF/X-4) avec polices incorporées ou vectorisées. PNG et JPEG sont acceptés.</p>
<h2>5. BAT</h2><p>Nous contrôlons chaque fichier puis vous envoyons un bon à tirer. <b>Rien n\'est imprimé avant votre approbation de la dernière version.</b> Vérifiez textes, orthographe, couleurs et dimensions.</p>');
$m->Output(__DIR__.'/../public/docs/guide-fichiers-ho-creative.pdf', 'F');
echo "ok\n";
