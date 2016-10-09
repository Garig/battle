<?php

function chargerClasse($classe)
{
    require $classe .'.class.php';
}
spl_autoload_register('chargerClasse');

session_start();

if (isset($_GET['deconnexion']))
{
    session_destroy();
    header('Location: .');
    exit();
}
$db = new PDO('mysql:host=localhost;dbname=test', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$manager = new PersonnagesManager($db);
if (isset($_SESSION['perso'])) // Si la session perso existe, on restaure l'objet.
{
    $perso = $_SESSION['perso'];
}
if (isset($_POST['creer']) && isset($_POST['nom'])) // Si on a voulu créer un personnage.
{
    switch ($_POST['type'])
    {
        case 'magicien' :
            $perso = new Magicien(['nom' => $_POST['nom']]);
        break;
        case 'guerrier' :
            $perso = new Guerrier(['nom' => $_POST['nom']]);
        break;
        default :
            $message = 'Le type du personnage est invalide.';
        break;
    }
    if (isset($perso)) // Si le type du personnage est valide, on a créé un personnage.
    {
        if (!$perso->nomValide())
        {
            $message = 'Le nom choisi est invalide.';
            unset($perso);
        }
        elseif ($manager->exists($perso->nom()))
        {
            $message = 'Le nom du personnage est déjà pris.';
            unset($perso);
        }
        else
        {
            $manager->add($perso);
        }
    }
}
elseif (isset($_POST['utiliser']) && isset($_POST['nom'])) // Si on a voulu utiliser un personnage.
{
    if ($manager->exists($_POST['nom'])) // Si celui-ci existe.
    {
        $perso = $manager->get($_POST['nom']);
    }
    else
    {
        $message = 'Ce personnage n\'existe pas !'; // S'il n'existe pas, on affichera ce message.
    }
}
elseif (isset($_GET['frapper'])) // Si on a cliqué sur un personnage pour le frapper.
{
    if (!isset($perso))
    {
        $message = 'Merci de créer un personnage ou de vous identifier.';
    }
    else
    {
        if (!$manager->exists((int) $_GET['frapper']))
        {
            $message = 'Le personnage que vous voulez frapper n\'existe pas !';
        }
        else
        {
            $persoAFrapper = $manager->get((int) $_GET['frapper']);
            $retour = $perso->frapper($persoAFrapper); // On stocke dans $retour les éventuelles erreurs ou messages que renvoie la méthode frapper.
            switch ($retour)
            {
                case Personnage::CEST_MOI :
                $message = 'Mais... pourquoi voulez-vous vous frapper ???';
                break;
                case Personnage::PERSONNAGE_FRAPPE :
                $message = 'Le personnage a bien été frappé !';
                $manager->update($perso);
                $manager->update($persoAFrapper);
                break;
                case Personnage::PERSONNAGE_TUE :
                $message = 'Vous avez tué ce personnage !';
                $manager->update($perso);
                $manager->delete($persoAFrapper);
                break;
                case Personnage::PERSO_ENDORMI :
                $message = 'Vous êtes endormi, vous ne pouvez pas frapper de personnage !';
                break;
            }
        }
    }
}
elseif (isset($_GET['ensorceler']))
{
    if (!isset($perso))
    {
        $message = 'Merci de créer un personnage ou de vous identifier.';
    }
    else
    {
    // Il faut bien vérifier que le personnage est un magicien.
        if ($perso->type() != 'magicien')
        {
            $message = 'Seuls les magiciens peuvent ensorceler des personnages !';
        }
        else
        {
            if (!$manager->exists((int) $_GET['ensorceler']))
            {
                $message = 'Le personnage que vous voulez frapper n\'existe pas !';
            }
            else
            {
                $persoAEnsorceler = $manager->get((int) $_GET['ensorceler']);
                $retour = $perso->lancerUnSort($persoAEnsorceler);
                switch ($retour)
                {
                    case Personnage::CEST_MOI :
                    $message = 'Mais pourquoi voulez-vous vous ensorceler ???';
                    break;
                    case Personnage::PERSONNAGE_ENSORCELE :
                    $message = 'Le personnage a bien été ensorcelé !';
                    $manager->update($perso);
                    $manager->update($persoAEnsorceler);
                    break;
                    case Personnage::PAS_DE_MAGIE :
                    $message = 'Vous n\'avez pas de magie !';
                    break;
                    case Personnage::PERSO_ENDORMI :
                    $message = 'Vous êtes endormi, vous ne pouvez pas lancer de sort !';
                    break;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
    <title>TP : Mini jeu de combat - Version 2</title>
    <meta charset="utf-8" />
    </head>
    <body>
        <p>Nombre de personnages créés : <?= $manager->count() ?></p>
<?php
if (isset($message)) // On a un message à afficher ?
{
    echo '<p>', $message, '</p>'; // Si oui, on l'affiche
}
if (isset($perso)) // Si on utilise un personnage (nouveau ou pas).
{
?>
        <p><a href="?deconnexion=1">Déconnexion</a></p>
        <fieldset>
            <legend>Mes informations</legend>
            <p>
            Type : <?= ucfirst($perso->type()) ?><br />
            Nom : <?= htmlspecialchars($perso->nom()) ?><br />
            Dégâts : <?= $perso->degats() ?><br />
<?php
// On affiche l'atout du personnage suivant son type.
switch ($perso->type())
{
    case 'magicien' :
    echo 'Magie : ';
    break;
    case 'guerrier' :
    echo 'Protection : ';
    break;
}
echo $perso->atout();
?>
            </p>
        </fieldset>

        <fieldset>
            <legend>Qui attaquer ?</legend>
            <p>
<?php
// On récupère tous les personnages par ordre alphabétique, dont le nom est différent de celui de notre personnage (on va pas se frapper nous-même :p).
$retourPersos = $manager->getList($perso->nom());
if (empty($retourPersos))
{
    echo 'Personne à frapper !';
}
else
{
    if ($perso->estEndormi())
    {
        echo 'Un magicien vous a endormi ! Vous allez vous réveiller dans ', $perso->reveil(), '.';
    }
    else
    {
        foreach ($retourPersos as $unPerso)
        {
            echo '<a href="?frapper=', $unPerso->id(), '">', htmlspecialchars($unPerso->nom()), '</a> (dégâts : ', $unPerso->degats(), ' | type : ', $unPerso->type(), ')';
            // On ajoute un lien pour lancer un sort si le personnage est un magicien.
            if ($perso->type() == 'magicien')
            {
                echo ' | <a href="?ensorceler=', $unPerso->id(), '">Lancer un sort</a>';
            }
            echo '<br />';
        }
    }
}
?>
            </p>
        </fieldset>
<?php
}
else
{
?>
        <form action="" method="post">
            <p>
            Nom : <input type="text" name="nom" maxlength="50" /> 
            <input type="submit" value="Utiliser ce personnage" name="utiliser" /><br />
            Type :
            <select name="type">
                <option value="magicien">Magicien</option>
                <option value="guerrier">Guerrier</option>
            </select>
            <input type="submit" value="Créer ce personnage" name="creer" />
            </p>
        </form>
<?php
}
?>
    </body>
</html>
<?php
if (isset($perso)) // Si on a créé un personnage, on le stocke dans une variable session afin d'économiser une requête SQL.
{
    $_SESSION['perso'] = $perso;
}

