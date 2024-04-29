<?php
/*
 * USE THIS FILE TO CREATE THE SCHEMA AND TABLES
 * It is necessary to provide a connection string with administrative privileges
 */
require_once 'lib/default_conf.php';

/*
 * Parameters to create the new schema and user
 * $schemaName: name of the DB schema
 * $serviceUser: username that the COVID19 service will use to access the new schema
 * $servicePassword: password that the COVID19 service will use to access the new schema
 */
$schemaName = 'COVID_KITS';
$serviceUser = 'COVID_KITS';
$servicePassword = 'yyyyy';

/*
 * Connection string for a user with administrative privileges.
 * Example:
 * $adminConnectionURI = 'mysql://root:xxxxx!@production.linkcareapp.com:3306/';
 */
$adminConnectionURI = '';

if (!$adminConnectionURI || !$serviceUser || !$servicePassword) {
    openErrorInfoView('Deploy script not configured. Contact a system administrator');
    exit(0);
}

/* Initialize the connection to the DB */
$dbConnResult = Database::init($adminConnectionURI);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    openErrorInfoView($err);
    exit(0);
}

$db = Database::getInstance();
$schema = DataModels::dataModel($schemaName);

$error = $db->createUser($serviceUser, $servicePassword, ($db->getType() == DbManager::ORACLE));
if ($error->getErrorCode()) {
    openErrorInfoView($error->getErrorMessage());
    exit(0);
}

$error = $db->createSchema($schema);
if ($error->getErrorCode()) {
    openErrorInfoView($error->getErrorMessage());
    exit(0);
}

$error = $db->grantDefaultPrivileges($serviceUser, $schemaName);
if ($error->getErrorCode()) {
    openErrorInfoView($error->getErrorMessage());
    exit(0);
}

$literals = serviceLiterals();
$sqlDescriptions = "INSERT INTO $schemaName.DESCRIPTIONS (ID_DESCRIPTION, DESCRIPTION_GROUP, DESCRIPTION_KEY) VALUES (:descriptionId, :groupName, :key)";
$sqlTranslations = "INSERT INTO $schemaName.DESCRIPTION_TRANSLATIONS (ID_DESCRIPTION, ISO2_LANGUAGE, DESCRIPTION) VALUES (:descriptionId, :isoLang, :description)";

$descriptionIds = [];
foreach ($literals as $description) {
    $group = $description['group'];
    $key = $description['key'];

    $arrVariables = [];
    $arrVariables[':groupName'] = $group;
    $arrVariables[':key'] = $key;

    $groupKey = $group . "|" . $key;
    if (array_key_exists($groupKey, $descriptionIds)) {
        $id = $descriptionIds[$groupKey];
    } else {
        $id = null;
        $db->getNextSequenceValue('SEQ_DESCRIPTIONS', $id);
        $error = $db->getError();
        if ($error->getErrorCode()) {
            openErrorInfoView($error->getErrorMessage());
            exit(1);
        }

        $arrVariables[':descriptionId'] = $id;
        $db->ExecuteBindQuery($sqlDescriptions, $arrVariables);
        $error = $db->getError();
        if ($error->getErrorCode()) {
            openErrorInfoView($error->getErrorMessage());
            exit(1);
        }
        $db->getLastInsertedId($id);
        $descriptionIds[$groupKey] = $id;
    }

    $arrVariables = [];
    $arrVariables[':descriptionId'] = $id;
    $arrVariables[':isoLang'] = $description['lang'];
    $arrVariables[':description'] = $description['desc'];
    $db->ExecuteBindQuery($sqlTranslations, $arrVariables);
    $error = $db->getError();
    if ($error->getErrorCode()) {
        openErrorInfoView($error->getErrorMessage());
        exit(1);
    }
}

openErrorInfoView("Database $schemaName created successfully");

/**
 * Opens the ErrorInfo view page
 *
 * @param string $err
 */
function openErrorInfoView($err) {
    $GLOBALS["VIEW_MODEL"] = $err;
    include "views/Header.html.php";
    include "views/ErrorInfo.html.php";
}

function serviceLiterals() {
    $literals[] = ['group' => 'Errors', 'key' => 'ADMISSION_ACTIVE', 'lang' => 'ca',
            'desc' => "No es pot crear una admissió perque el pacient actualment en té una activa."];
    $literals[] = ['group' => 'Errors', 'key' => 'ADMISSION_ACTIVE', 'lang' => 'en',
            'desc' => "It is not possible to create an admission because the participant already has one active."];
    $literals[] = ['group' => 'Errors', 'key' => 'ADMISSION_ACTIVE', 'lang' => 'es',
            'desc' => "No se puede crear una admisión porque el paciente actualmente tiene una activa."];
    $literals[] = ['group' => 'Errors', 'key' => 'ADMISSION_ACTIVE', 'lang' => 'fr',
            'desc' => "Il n'est pas possible de créer une admission car le participant en a déjà une active."];
    $literals[] = ['group' => 'Errors', 'key' => 'ADMISSION_ACTIVE', 'lang' => 'it',
            'desc' => "Non è possibile creare un'ammissione perché il partecipante ne ha già uno attivo."];
    $literals[] = ['group' => 'Errors', 'key' => 'ADMISSION_ACTIVE', 'lang' => 'zh', 'desc' => "因受测者已有一项激活的准入因此无法创建新的准入"];
    $literals[] = ['group' => 'Errors', 'key' => 'DB_CONNECTION_ERROR', 'lang' => 'ca', 'desc' => "La connexió de la BBDD ha fallat"];
    $literals[] = ['group' => 'Errors', 'key' => 'DB_CONNECTION_ERROR', 'lang' => 'en', 'desc' => "Connection to DB failed"];
    $literals[] = ['group' => 'Errors', 'key' => 'DB_CONNECTION_ERROR', 'lang' => 'es', 'desc' => "La conexión a la BBDD ha fallado"];
    $literals[] = ['group' => 'Errors', 'key' => 'DB_CONNECTION_ERROR', 'lang' => 'fr', 'desc' => "La connexion à la DB a échoué"];
    $literals[] = ['group' => 'Errors', 'key' => 'DB_CONNECTION_ERROR', 'lang' => 'it', 'desc' => "Connessione a DB non riuscita"];
    $literals[] = ['group' => 'Errors', 'key' => 'DB_CONNECTION_ERROR', 'lang' => 'zh', 'desc' => "数据连接失败"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_KIT', 'lang' => 'ca', 'desc' => "El kit seleccionat és invàlid"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_KIT', 'lang' => 'en', 'desc' => "The selected kit is invalid"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_KIT', 'lang' => 'es', 'desc' => "El kit seleccionado es inválido"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_KIT', 'lang' => 'fr', 'desc' => "Le kit sélectionné n'est pas valide"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_KIT', 'lang' => 'it', 'desc' => "Il kit selezionato non è valido"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_KIT', 'lang' => 'zh', 'desc' => "所选试剂盒是无效试剂盒"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_STATUS', 'lang' => 'ca', 'desc' => "El kit seleccionat conté un estat invàlid"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_STATUS', 'lang' => 'en', 'desc' => "The selected kit has an invalid status"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_STATUS', 'lang' => 'es', 'desc' => "El estado del kit contiene un valor inválido"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_STATUS', 'lang' => 'fr', 'desc' => "Le kit sélectionné a un statut invalide"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_STATUS', 'lang' => 'it', 'desc' => "Lo stato del kit selezionato non è valido"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVALID_STATUS', 'lang' => 'zh', 'desc' => "所选试剂盒处于无效状态"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVITATION_ACTIVE', 'lang' => 'ca',
            'desc' => "No es pot crear una nova admissió perque existeix altre amb la mateixa referència d'invitació i el termini per crear una nova encara no ha passat"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVITATION_ACTIVE', 'lang' => 'en',
            'desc' => "Cannot create a new admission because there exists another one with the same invitation reference and the term to create a new one has not passed yet"];
    $literals[] = ['group' => 'Errors', 'key' => 'INVITATION_ACTIVE', 'lang' => 'es',
            'desc' => "No se puede crear una nueva admisión porque existe otra con la misma referencia de invitación y el plazo para crear una nueva todavía no ha pasado"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_ALREADY_USED', 'lang' => 'ca',
            'desc' => "Aquest Kit ja s'ha fet servir amb un altre participant"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_ALREADY_USED', 'lang' => 'en',
            'desc' => "This Kit has already been used by another participant"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_ALREADY_USED', 'lang' => 'es', 'desc' => "Este Kit ya se ha usado con otro participante"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_ALREADY_USED', 'lang' => 'fr', 'desc' => "Ce kit a déjà été utilisé par un autre participant"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_ALREADY_USED', 'lang' => 'it',
            'desc' => "Questo Kit è già stato utilizzato da un altro partecipante"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_ALREADY_USED', 'lang' => 'zh', 'desc' => "此试剂盒已被其他人使用"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_EXPIRED', 'lang' => 'ca', 'desc' => "El kit està caducat"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_EXPIRED', 'lang' => 'en', 'desc' => "The kit has expired"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_EXPIRED', 'lang' => 'es', 'desc' => "El kit está caducado"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_EXPIRED', 'lang' => 'fr', 'desc' => "Le kit a expiré"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_EXPIRED', 'lang' => 'it', 'desc' => "Il kit è scaduto"];
    $literals[] = ['group' => 'Errors', 'key' => 'KIT_EXPIRED', 'lang' => 'zh', 'desc' => "此试剂盒已失效"];
    $literals[] = ['group' => 'Errors', 'key' => 'MAX_ROUNDS_EXCEEDED', 'lang' => 'ca',
            'desc' => "El nombre màxim de rondes per aquesta prescripció s'ha superat"];
    $literals[] = ['group' => 'Errors', 'key' => 'MAX_ROUNDS_EXCEEDED', 'lang' => 'en',
            'desc' => "The maximum number of rounds for this prescription has been exceeded"];
    $literals[] = ['group' => 'Errors', 'key' => 'MAX_ROUNDS_EXCEEDED', 'lang' => 'es',
            'desc' => "El número máximo de rondas para esta prescripción se ha superado"];
    $literals[] = ['group' => 'Errors', 'key' => 'MAX_ROUNDS_EXCEEDED', 'lang' => 'fr',
            'desc' => "Le nombre maximum de tours pour cette prescription a été dépassé"];
    $literals[] = ['group' => 'Errors', 'key' => 'MAX_ROUNDS_EXCEEDED', 'lang' => 'it',
            'desc' => "Il numero massimo di turni per questa prescrizione è stato superato"];
    $literals[] = ['group' => 'Errors', 'key' => 'MAX_ROUNDS_EXCEEDED', 'lang' => 'zh', 'desc' => "已超过该处方所允许的最大轮数"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_ALREADY_USED', 'lang' => 'ca',
            'desc' => "El ID de Prescripció ja s'ha fet servir per altre pacient"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_ALREADY_USED', 'lang' => 'en',
            'desc' => "The Prescription ID has already been used for another patient"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_ALREADY_USED', 'lang' => 'es',
            'desc' => "El ID de Prescripción ya ha sido usado para otro paciente"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_ALREADY_USED', 'lang' => 'fr',
            'desc' => "L'ID de prescription a déjà été utilisé pour un autre patient"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_ALREADY_USED', 'lang' => 'it',
            'desc' => "L'ID prescrizione è già stato utilizzato per un altro paziente"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_ALREADY_USED', 'lang' => 'zh', 'desc' => "该处方编号已用于另一受测者"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_EXPIRED', 'lang' => 'ca', 'desc' => "La prescripció ha expirat"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_EXPIRED', 'lang' => 'en', 'desc' => "The prescription has expired"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_EXPIRED', 'lang' => 'es', 'desc' => "La prescripción ha expirado"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_EXPIRED', 'lang' => 'fr', 'desc' => "L'ordonnance est expirée"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_EXPIRED', 'lang' => 'it', 'desc' => "La prescrizione è scaduta"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_EXPIRED', 'lang' => 'zh', 'desc' => "该处方已过期"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_MISSING', 'lang' => 'ca', 'desc' => "Manca la informació de la prescripció"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_MISSING', 'lang' => 'en', 'desc' => "Missing prescription information"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_MISSING', 'lang' => 'es', 'desc' => "Falta la información de la prescripción"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_MISSING', 'lang' => 'fr', 'desc' => "Informations de prescription manquantes"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_MISSING', 'lang' => 'it', 'desc' => "Informazioni mancanti sulla prescrizione"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_MISSING', 'lang' => 'zh', 'desc' => "缺少处方信息"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_WRONG_FORMAT', 'lang' => 'ca', 'desc' => "Format incorrecte de la prescripció"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_WRONG_FORMAT', 'lang' => 'en', 'desc' => "Wrong prescription format"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_WRONG_FORMAT', 'lang' => 'es', 'desc' => "Formato incorrecto de la prescripción"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_WRONG_FORMAT', 'lang' => 'fr', 'desc' => "Mauvais format de prescription"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_WRONG_FORMAT', 'lang' => 'it', 'desc' => "Formato di prescrizione errato"];
    $literals[] = ['group' => 'Errors', 'key' => 'PRESCRIPTION_WRONG_FORMAT', 'lang' => 'zh', 'desc' => "处方格式错误"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_FORBIDDEN', 'lang' => 'ca', 'desc' => "No té accés a la subscripció d'aquest usuari"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_FORBIDDEN', 'lang' => 'en',
            'desc' => "You don't have access to this user's subscription"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_FORBIDDEN', 'lang' => 'es',
            'desc' => "No tiene acceso a la suscripción de este usuario"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_FORBIDDEN', 'lang' => 'fr',
            'desc' => "Vous n'avez pas accès à l'abonnement de cet utilisateur"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_FORBIDDEN', 'lang' => 'it',
            'desc' => "Non hai accesso all'abbonamento di questo utente"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_FORBIDDEN', 'lang' => 'zh', 'desc' => "您无权访问此用户的订阅"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_NOT_FOUND', 'lang' => 'ca',
            'desc' => "No s'ha pogut crear una admissió perqué no s'ha trobat la subscripció al programa"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_NOT_FOUND', 'lang' => 'en',
            'desc' => "Cannot create an admission because the subscription to the program was not found"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_NOT_FOUND', 'lang' => 'es',
            'desc' => "No se ha podido crear una admisión porque no se ha encontrado la suscripción al programa"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_NOT_FOUND', 'lang' => 'fr',
            'desc' => "Impossible de créer une admission car l'abonnement au programme n'a pas été trouvé"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_NOT_FOUND', 'lang' => 'it',
            'desc' => "Impossibile creare un'ammissione perché la sottoscrizione al programma non è stata trovata"];
    $literals[] = ['group' => 'Errors', 'key' => 'SUBSCRIPTION_NOT_FOUND', 'lang' => 'zh', 'desc' => "因为没有找到订阅该项目的记录，所以无法创建录入信息"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.ca', 'lang' => 'ca', 'desc' => "Català"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.ca', 'lang' => 'en', 'desc' => "Catalan"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.ca', 'lang' => 'es', 'desc' => "Catalán"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.ca', 'lang' => 'fr', 'desc' => "Catalan"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.ca', 'lang' => 'it', 'desc' => "Catalano"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.ca', 'lang' => 'zh', 'desc' => "加泰罗尼亚语"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.en', 'lang' => 'ca', 'desc' => "Anglès"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.en', 'lang' => 'en', 'desc' => "English"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.en', 'lang' => 'es', 'desc' => "Inglés"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.en', 'lang' => 'fr', 'desc' => "Anglais"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.en', 'lang' => 'it', 'desc' => "Inglese"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.en', 'lang' => 'zh', 'desc' => "英语"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.es', 'lang' => 'ca', 'desc' => "Espanyol"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.es', 'lang' => 'en', 'desc' => "Spanish"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.es', 'lang' => 'es', 'desc' => "Español"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.es', 'lang' => 'fr', 'desc' => "Espagnol"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.es', 'lang' => 'it', 'desc' => "Spagnolo"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.es', 'lang' => 'zh', 'desc' => "西班牙语"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.fr', 'lang' => 'ca', 'desc' => "Francès"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.fr', 'lang' => 'en', 'desc' => "French"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.fr', 'lang' => 'es', 'desc' => "Francés"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.fr', 'lang' => 'fr', 'desc' => "Français"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.fr', 'lang' => 'it', 'desc' => "Francese"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.fr', 'lang' => 'zh', 'desc' => "法语"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.it', 'lang' => 'ca', 'desc' => "Italià"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.it', 'lang' => 'en', 'desc' => "Italian"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.it', 'lang' => 'es', 'desc' => "Italiano"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.it', 'lang' => 'fr', 'desc' => "Italien"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.it', 'lang' => 'it', 'desc' => "Italiano"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.it', 'lang' => 'zh', 'desc' => "意大利语"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.zh', 'lang' => 'ca', 'desc' => "Xinès"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.zh', 'lang' => 'en', 'desc' => "Chinese"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.zh', 'lang' => 'es', 'desc' => "Chino"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.zh', 'lang' => 'fr', 'desc' => "Chinois"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.zh', 'lang' => 'it', 'desc' => "Cinese"];
    $literals[] = ['group' => 'Language', 'key' => 'KitInfo.Language.zh', 'lang' => 'zh', 'desc' => "中文"];
    $literals[] = ['group' => 'Status', 'key' => 'ASSIGNED', 'lang' => 'ca', 'desc' => "ASSIGNAT"];
    $literals[] = ['group' => 'Status', 'key' => 'ASSIGNED', 'lang' => 'en', 'desc' => "ASSIGNED"];
    $literals[] = ['group' => 'Status', 'key' => 'ASSIGNED', 'lang' => 'es', 'desc' => "ASIGNADO"];
    $literals[] = ['group' => 'Status', 'key' => 'ASSIGNED', 'lang' => 'fr', 'desc' => "ATTRIBUÉ"];
    $literals[] = ['group' => 'Status', 'key' => 'ASSIGNED', 'lang' => 'it', 'desc' => "Assegnate"];
    $literals[] = ['group' => 'Status', 'key' => 'ASSIGNED', 'lang' => 'zh', 'desc' => "已指定使用者"];
    $literals[] = ['group' => 'Status', 'key' => 'DISCARDED', 'lang' => 'ca', 'desc' => "DESCARTAT"];
    $literals[] = ['group' => 'Status', 'key' => 'DISCARDED', 'lang' => 'en', 'desc' => "DISCARDED"];
    $literals[] = ['group' => 'Status', 'key' => 'DISCARDED', 'lang' => 'es', 'desc' => "DESCARTADO"];
    $literals[] = ['group' => 'Status', 'key' => 'DISCARDED', 'lang' => 'fr', 'desc' => "MIS AU REBUT"];
    $literals[] = ['group' => 'Status', 'key' => 'DISCARDED', 'lang' => 'it', 'desc' => "Scartato"];
    $literals[] = ['group' => 'Status', 'key' => 'DISCARDED', 'lang' => 'zh', 'desc' => "弃用"];
    $literals[] = ['group' => 'Status', 'key' => 'EXPIRED', 'lang' => 'ca', 'desc' => "CADUCAT"];
    $literals[] = ['group' => 'Status', 'key' => 'EXPIRED', 'lang' => 'en', 'desc' => "EXPIRED"];
    $literals[] = ['group' => 'Status', 'key' => 'EXPIRED', 'lang' => 'es', 'desc' => "CADUCADO"];
    $literals[] = ['group' => 'Status', 'key' => 'EXPIRED', 'lang' => 'fr', 'desc' => "EXPIRÉ"];
    $literals[] = ['group' => 'Status', 'key' => 'EXPIRED', 'lang' => 'it', 'desc' => "Scaduto"];
    $literals[] = ['group' => 'Status', 'key' => 'EXPIRED', 'lang' => 'zh', 'desc' => "已过期"];
    $literals[] = ['group' => 'Status', 'key' => 'INSERT_RESULTS', 'lang' => 'ca', 'desc' => "PENDENT RESULTATS"];
    $literals[] = ['group' => 'Status', 'key' => 'INSERT_RESULTS', 'lang' => 'en', 'desc' => "RESULTS PENDING"];
    $literals[] = ['group' => 'Status', 'key' => 'INSERT_RESULTS', 'lang' => 'es', 'desc' => "PENDIENTE RESULTADOS"];
    $literals[] = ['group' => 'Status', 'key' => 'INSERT_RESULTS', 'lang' => 'fr', 'desc' => "RÉSULTATS EN ATTENTE"];
    $literals[] = ['group' => 'Status', 'key' => 'INSERT_RESULTS', 'lang' => 'it', 'desc' => "RISULTATI IN ATTESA"];
    $literals[] = ['group' => 'Status', 'key' => 'INSERT_RESULTS', 'lang' => 'zh', 'desc' => "等待结果中"];
    $literals[] = ['group' => 'Status', 'key' => 'NOT_USED', 'lang' => 'ca', 'desc' => "NO USAT"];
    $literals[] = ['group' => 'Status', 'key' => 'NOT_USED', 'lang' => 'en', 'desc' => "NOT USED"];
    $literals[] = ['group' => 'Status', 'key' => 'NOT_USED', 'lang' => 'es', 'desc' => "NO USADO"];
    $literals[] = ['group' => 'Status', 'key' => 'NOT_USED', 'lang' => 'fr', 'desc' => "NON UTILISÉ"];
    $literals[] = ['group' => 'Status', 'key' => 'NOT_USED', 'lang' => 'it', 'desc' => "NON IN USO"];
    $literals[] = ['group' => 'Status', 'key' => 'NOT_USED', 'lang' => 'zh', 'desc' => "未使用"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING', 'lang' => 'ca', 'desc' => "PROCESSANT"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING', 'lang' => 'en', 'desc' => "PROCESSING"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING', 'lang' => 'es', 'desc' => "PROCESANDO"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING', 'lang' => 'fr', 'desc' => "EN TRAITEMENT"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING', 'lang' => 'it', 'desc' => "Elaborazione"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING', 'lang' => 'zh', 'desc' => "处理中"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING_5MIN', 'lang' => 'ca', 'desc' => "PROCESSANT (Resultats en <5 min.)"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING_5MIN', 'lang' => 'en', 'desc' => "PROCESSING (Results in <5 min.)"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING_5MIN', 'lang' => 'es', 'desc' => "PROCESANDO (Resultados en <5 min.)"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING_5MIN', 'lang' => 'fr', 'desc' => "TRAITEMENT (Résultats en moins de 5 min.)"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING_5MIN', 'lang' => 'it', 'desc' => "ELABORAZIONE (Risultati in <5 min.)"];
    $literals[] = ['group' => 'Status', 'key' => 'PROCESSING_5MIN', 'lang' => 'zh', 'desc' => "处理中(五分钟内出结果)"];
    $literals[] = ['group' => 'Status', 'key' => 'USED', 'lang' => 'ca', 'desc' => "UTILITZAT"];
    $literals[] = ['group' => 'Status', 'key' => 'USED', 'lang' => 'en', 'desc' => "USED"];
    $literals[] = ['group' => 'Status', 'key' => 'USED', 'lang' => 'es', 'desc' => "USADO"];
    $literals[] = ['group' => 'Status', 'key' => 'USED', 'lang' => 'fr', 'desc' => "UTILISÉ"];
    $literals[] = ['group' => 'Status', 'key' => 'USED', 'lang' => 'it', 'desc' => "Utilizzato"];
    $literals[] = ['group' => 'Status', 'key' => 'USED', 'lang' => 'zh', 'desc' => "已使用"];
    $literals[] = ['group' => 'Web', 'key' => 'Admission.AlreadyActive', 'lang' => 'ca',
            'desc' => "Actualment ja existeix una admissió activa pel pacient. Ha de finalitzar-la abans de començar una nova"];
    $literals[] = ['group' => 'Web', 'key' => 'Admission.AlreadyActive', 'lang' => 'en',
            'desc' => "There already exists an active admission for the patient. You must discharge it before starting a new one."];
    $literals[] = ['group' => 'Web', 'key' => 'Admission.AlreadyActive', 'lang' => 'es',
            'desc' => "Actualmente ya existe una admisión activa para el paciente. Debe finalizarla antes de comenzar una nueva"];
    $literals[] = ['group' => 'Web', 'key' => 'Admission.AlreadyActive', 'lang' => 'fr',
            'desc' => "Il existe déjà une admission active pour le patient. Vous devez le décharger avant d'en commencer un nouveau."];
    $literals[] = ['group' => 'Web', 'key' => 'Admission.AlreadyActive', 'lang' => 'it',
            'desc' => "Esiste già un ricovero attivo per il paziente. È necessario scaricarlo prima di avviarne uno nuovo."];
    $literals[] = ['group' => 'Web', 'key' => 'Admission.AlreadyActive', 'lang' => 'zh', 'desc' => "已存在一项激活的受测者记录，您必须删除后才能开始一项新的"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.EnterResults', 'lang' => 'ca', 'desc' => "Entrar resultats"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.EnterResults', 'lang' => 'en', 'desc' => "Enter results"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.EnterResults', 'lang' => 'es', 'desc' => "Entrar resultados"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.EnterResults', 'lang' => 'fr', 'desc' => "Entrer les résultats"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.EnterResults', 'lang' => 'it', 'desc' => "Immettere i risultati"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.EnterResults', 'lang' => 'zh', 'desc' => "输入结果"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Register', 'lang' => 'ca', 'desc' => "Registrar un nou test"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Register', 'lang' => 'en', 'desc' => "Register a new test"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Register', 'lang' => 'es', 'desc' => "Registrar un nuevo test"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Register', 'lang' => 'fr', 'desc' => "Enregistrer un nouveau test"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Register', 'lang' => 'it', 'desc' => "Registra un nuovo test"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Register', 'lang' => 'zh', 'desc' => "登记新的检测"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Video', 'lang' => 'ca', 'desc' => "Veure el vídeo tutorial"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Video', 'lang' => 'en', 'desc' => "Watch the video tutorial"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Video', 'lang' => 'es', 'desc' => "Ver el video tutorial"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Video', 'lang' => 'fr', 'desc' => "Regarder le tutoriel vidéo"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Video', 'lang' => 'it', 'desc' => "Guarda il video tutorial"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.Video', 'lang' => 'zh', 'desc' => "观看视频演示"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.ViewResults', 'lang' => 'ca', 'desc' => "Veure resultats"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.ViewResults', 'lang' => 'en', 'desc' => "View results"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.ViewResults', 'lang' => 'es', 'desc' => "Ver resultados"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.ViewResults', 'lang' => 'fr', 'desc' => "Voir les résultats"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.ViewResults', 'lang' => 'it', 'desc' => "Vedi i risultati"];
    $literals[] = ['group' => 'Web', 'key' => 'Autoadmin.Button.ViewResults', 'lang' => 'zh', 'desc' => "查看结果"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.iOS', 'lang' => 'ca',
            'desc' => "L'accés a la càmera de vídeo des de iPhone/iPad només és compatible amb Safari. Canvieu a l'aplicació Safari per continuar."];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.iOS', 'lang' => 'en',
            'desc' => "The access to video camera from iPhone/iPad is only supported in Safari. Please switch to your Safari app to continue."];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.iOS', 'lang' => 'es',
            'desc' => "El acceso a la cámara de video desde iPhone/iPad solo es compatible con Safari. Cambie a su aplicación Safari para continuar."];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.iOS', 'lang' => 'fr',
            'desc' => "L'accès à la caméra vidéo depuis l'iPhone / iPad n'est pris en charge que dans Safari. Veuillez passer à votre application Safari pour continuer."];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.iOS', 'lang' => 'it',
            'desc' => "L'accesso alla videocamera da iPhone / iPad è supportato solo in Safari. Passa alla tua app Safari per continuare."];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.iOS', 'lang' => 'zh', 'desc' => "仅在Safari应用中支持通过iPhone / iPad使用相机。 请切换到Safari应用以继续。"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.Open.Message', 'lang' => 'ca', 'desc' => "Premi el següent botó per tal d'obrir l'escàner"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.Open.Message', 'lang' => 'en', 'desc' => "Click below to open scanner"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.Open.Message', 'lang' => 'es', 'desc' => "Presione el siguiente botón para abrir el escaner"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.Open.Message', 'lang' => 'fr', 'desc' => "Cliquez ci-dessous pour ouvrir le scanner"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.Open.Message', 'lang' => 'it', 'desc' => "Clicca qui sotto per aprire lo scanner"];
    $literals[] = ['group' => 'Web', 'key' => 'Camera.Open.Message', 'lang' => 'zh', 'desc' => "点击下方开始扫描"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Button.CheckParticipant', 'lang' => 'ca', 'desc' => "Escanejar QR"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Button.CheckParticipant', 'lang' => 'en', 'desc' => "Scan QR"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Button.CheckParticipant', 'lang' => 'es', 'desc' => "Escanear QR"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Button.CheckParticipant', 'lang' => 'fr', 'desc' => "Scanner QR"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Button.CheckParticipant', 'lang' => 'it', 'desc' => "Scansiona QR"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Title', 'lang' => 'ca', 'desc' => "Porter digital Covid19"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Title', 'lang' => 'en', 'desc' => "Covid19 Gatekeeper"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Title', 'lang' => 'es', 'desc' => "Portero digital Covid19"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Title', 'lang' => 'fr', 'desc' => "Portier numérique Covid19"];
    $literals[] = ['group' => 'Web', 'key' => 'Gatekeeper.Title', 'lang' => 'it', 'desc' => "Portiere digitale Covid19"];
    $literals[] = ['group' => 'Web', 'key' => 'Head.Title', 'lang' => 'ca', 'desc' => "Linkcare Kit Info App"];
    $literals[] = ['group' => 'Web', 'key' => 'Head.Title', 'lang' => 'en', 'desc' => "Linkcare Kit Info App"];
    $literals[] = ['group' => 'Web', 'key' => 'Head.Title', 'lang' => 'es', 'desc' => "Linkcare Kit Info App"];
    $literals[] = ['group' => 'Web', 'key' => 'Head.Title', 'lang' => 'fr', 'desc' => "Application d'informations sur le kit Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Head.Title', 'lang' => 'it', 'desc' => "Linkcare Kit Info App"];
    $literals[] = ['group' => 'Web', 'key' => 'Head.Title', 'lang' => 'zh', 'desc' => "Linkcare试剂盒信息App"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Close', 'lang' => 'ca', 'desc' => "TANCAR"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Close', 'lang' => 'en', 'desc' => "CLOSE"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Close', 'lang' => 'es', 'desc' => "CERRAR"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Close', 'lang' => 'fr', 'desc' => "FERMER"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Close', 'lang' => 'it', 'desc' => "Vicino"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Close', 'lang' => 'zh', 'desc' => "关闭"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Continue', 'lang' => 'ca', 'desc' => "CONTINUAR"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Continue', 'lang' => 'en', 'desc' => "CONTINUE"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Continue', 'lang' => 'es', 'desc' => "CONTINUAR"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Continue', 'lang' => 'fr', 'desc' => "CONTINUER"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Continue', 'lang' => 'it', 'desc' => "Continuare"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Continue', 'lang' => 'zh', 'desc' => "继续"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Discard', 'lang' => 'ca', 'desc' => "Descartar kit"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Discard', 'lang' => 'en', 'desc' => "Discard kit"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Discard', 'lang' => 'es', 'desc' => "Descartar kit"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Discard', 'lang' => 'fr', 'desc' => "Jeter le kit"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Discard', 'lang' => 'it', 'desc' => "Kit scartamento"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Discard', 'lang' => 'zh', 'desc' => "弃用试剂盒"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.EarlyProceed', 'lang' => 'ca', 'desc' => "ENTRAR RESULTATS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.EarlyProceed', 'lang' => 'en', 'desc' => "ENTER RESULTS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.EarlyProceed', 'lang' => 'es', 'desc' => "ENTRAR RESULTADOS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.EarlyProceed', 'lang' => 'fr', 'desc' => "ENTRER LES RÉSULTATS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.EarlyProceed', 'lang' => 'it', 'desc' => "IMMETTERE I RISULTATI"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.EarlyProceed', 'lang' => 'zh', 'desc' => "输入结果"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.InsertResults', 'lang' => 'ca', 'desc' => "ENTRAR RESULTATS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.InsertResults', 'lang' => 'en', 'desc' => "ENTER RESULTS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.InsertResults', 'lang' => 'es', 'desc' => "ENTRAR RESULTADOS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.InsertResults', 'lang' => 'fr', 'desc' => "ENTRER LES RÉSULTATS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.InsertResults', 'lang' => 'it', 'desc' => "IMMETTERE I RISULTATI"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.InsertResults', 'lang' => 'zh', 'desc' => "输入结果"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Proceed', 'lang' => 'ca', 'desc' => "PROCESSAR"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Proceed', 'lang' => 'en', 'desc' => "PROCESS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Proceed', 'lang' => 'es', 'desc' => "PROCESAR"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Proceed', 'lang' => 'fr', 'desc' => "PROCESSUS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Proceed', 'lang' => 'it', 'desc' => "Processo"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Button.Proceed', 'lang' => 'zh', 'desc' => "继续"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Confirm', 'lang' => 'ca', 'desc' => "Està segur de voler descartar aquest kit?"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Confirm', 'lang' => 'en', 'desc' => "Are you sure that you want to discard the selected kit?"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Confirm', 'lang' => 'es', 'desc' => "¿Está seguro de que quiere descartar el kit?"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Confirm', 'lang' => 'fr', 'desc' => "Voulez-vous vraiment supprimer le kit sélectionné?"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Confirm', 'lang' => 'it', 'desc' => "Si è sicuri di voler scartare il kit selezionato?"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Confirm', 'lang' => 'zh', 'desc' => "您确定要弃用选定的试剂盒吗？"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.BatchNumber', 'lang' => 'ca', 'desc' => "LOT"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.BatchNumber', 'lang' => 'en', 'desc' => "BATCH NUMBER"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.BatchNumber', 'lang' => 'es', 'desc' => "LOTE"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.BatchNumber', 'lang' => 'fr', 'desc' => "NUMÉRO DE LOT"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.BatchNumber', 'lang' => 'it', 'desc' => "NUMERO LOTTO"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.BatchNumber', 'lang' => 'zh', 'desc' => "产品批次"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ExpirationDate', 'lang' => 'ca', 'desc' => "DATA DE CADUCITAT"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ExpirationDate', 'lang' => 'en', 'desc' => "EXPIRATION DATE"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ExpirationDate', 'lang' => 'es', 'desc' => "FECHA DE CADUCIDAD"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ExpirationDate', 'lang' => 'fr', 'desc' => "DATE D'EXPIRATION"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ExpirationDate', 'lang' => 'it', 'desc' => "DATA DI SCADENZA"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ExpirationDate', 'lang' => 'zh', 'desc' => "有效期至"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ManufacturedIn', 'lang' => 'ca', 'desc' => "FABRICAT A"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ManufacturedIn', 'lang' => 'en', 'desc' => "MANUFACTURED IN"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ManufacturedIn', 'lang' => 'es', 'desc' => "FABRICADO EN"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ManufacturedIn', 'lang' => 'fr', 'desc' => "FABRIQUÉ EN"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ManufacturedIn', 'lang' => 'it', 'desc' => "FABBRICATI IN"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.ManufacturedIn', 'lang' => 'zh', 'desc' => "生产地"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.Status', 'lang' => 'ca', 'desc' => "ESTAT"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.Status', 'lang' => 'en', 'desc' => "STATUS"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.Status', 'lang' => 'es', 'desc' => "ESTADO"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.Status', 'lang' => 'fr', 'desc' => "STATUT"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.Status', 'lang' => 'it', 'desc' => "Stato"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Label.Status', 'lang' => 'zh', 'desc' => "产品状态"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Title.InfoFromKitId', 'lang' => 'ca', 'desc' => "INFORMACIÓ DEL KIT AMB ID #{{kit_id}}"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Title.InfoFromKitId', 'lang' => 'en', 'desc' => "INFO FROM KIT ID #{{kit_id}}"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Title.InfoFromKitId', 'lang' => 'es', 'desc' => "INFORMACIÓN DEL KIT CON ID #{{kit_id}}"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Title.InfoFromKitId', 'lang' => 'fr', 'desc' => "INFO DU KIT ID #{{kit_id}}"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Title.InfoFromKitId', 'lang' => 'it', 'desc' => "INFO DALL'ID KIT #{{kit_id}}"];
    $literals[] = ['group' => 'Web', 'key' => 'KitInfo.Title.InfoFromKitId', 'lang' => 'zh', 'desc' => "试剂盒（产品编号#{{kit_id}}）的相关信息"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Button.Start', 'lang' => 'ca', 'desc' => "COMENÇAR"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Button.Start', 'lang' => 'en', 'desc' => "START"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Button.Start', 'lang' => 'es', 'desc' => "EMPEZAR"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Button.Start', 'lang' => 'fr', 'desc' => "DÉBUT"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Button.Start', 'lang' => 'it', 'desc' => "INIZIARE"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Button.Start', 'lang' => 'zh', 'desc' => "开始"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Email.Label', 'lang' => 'ca', 'desc' => "Email"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Email.Label', 'lang' => 'en', 'desc' => "Email"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Email.Label', 'lang' => 'es', 'desc' => "Email"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Email.Label', 'lang' => 'fr', 'desc' => "Courrier électronique"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Email.Label', 'lang' => 'it', 'desc' => "Posta elettronica"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Email.Label', 'lang' => 'zh', 'desc' => "电子邮箱"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Expires.Label', 'lang' => 'ca', 'desc' => "Data expiració"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Expires.Label', 'lang' => 'en', 'desc' => "Expiration date"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Expires.Label', 'lang' => 'es', 'desc' => "Fecha expiración"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Expires.Label', 'lang' => 'fr', 'desc' => "Date d'expiration"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Expires.Label', 'lang' => 'it', 'desc' => "Data di scadenza"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Expires.Label', 'lang' => 'zh', 'desc' => "失效日期"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Id.Label', 'lang' => 'ca', 'desc' => "ID de la prescripció"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Id.Label', 'lang' => 'en', 'desc' => "Prescription ID"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Id.Label', 'lang' => 'es', 'desc' => "ID de la prescripción"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Id.Label', 'lang' => 'fr', 'desc' => "ID d'ordonnance"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Id.Label', 'lang' => 'it', 'desc' => "ID prescrizione"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Id.Label', 'lang' => 'zh', 'desc' => "处方编号"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Info', 'lang' => 'ca',
            'desc' => "O escanegi el codi QR de la prescripció del participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Info', 'lang' => 'en', 'desc' => "Or scan the QR code on the participant's prescription"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Info', 'lang' => 'es',
            'desc' => "O escanee el código QR en la prescripción del participante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Info', 'lang' => 'fr',
            'desc' => "Ou scannez le code QR sur la prescription du participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Info', 'lang' => 'it',
            'desc' => "Oppure scansiona il codice QR sulla prescrizione del partecipante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Info', 'lang' => 'zh', 'desc' => "或者扫描受测者处方上的二维码"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label', 'lang' => 'ca', 'desc' => "Introduïu manualment un ID de participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label', 'lang' => 'en', 'desc' => "Manually enter a participant ID"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label', 'lang' => 'es', 'desc' => "Ingrese manualmente un ID de participante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label', 'lang' => 'fr', 'desc' => "Saisissez manuellement un identifiant de participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label', 'lang' => 'it', 'desc' => "Immettere manualmente un ID partecipante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label', 'lang' => 'zh', 'desc' => "手动输入受测者编号"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label.Checkbox', 'lang' => 'ca',
            'desc' => "Marqueu aquí i premeu 'COMENÇAR' per registrar el participant manualment"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label.Checkbox', 'lang' => 'en',
            'desc' => "Check here and press 'START' to register the participant manually"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label.Checkbox', 'lang' => 'es',
            'desc' => "Marque aquí y presione 'EMPEZAR' para registrar al participante manualmente"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label.Checkbox', 'lang' => 'fr',
            'desc' => "Vérifiez ici et appuyez sur 'DÉBUT' pour enregistrer le participant manuellement"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label.Checkbox', 'lang' => 'it',
            'desc' => "Controlla qui e premi 'INIZIARE' per registrare manualmente il partecipante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Label.Checkbox', 'lang' => 'zh', 'desc' => "勾选此处并在下方点击“开始”以对参与者进行人工注册"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.ParticipantId.Label', 'lang' => 'ca', 'desc' => "ID del participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.ParticipantId.Label', 'lang' => 'en', 'desc' => "Participant ID"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.ParticipantId.Label', 'lang' => 'es', 'desc' => "ID del participante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.ParticipantId.Label', 'lang' => 'fr', 'desc' => "ID de participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.ParticipantId.Label', 'lang' => 'it', 'desc' => "ID partecipante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.ParticipantId.Label', 'lang' => 'zh', 'desc' => "受测者编号"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.PatientName.Label', 'lang' => 'ca', 'desc' => "Participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.PatientName.Label', 'lang' => 'en', 'desc' => "Participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.PatientName.Label', 'lang' => 'es', 'desc' => "Participante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.PatientName.Label', 'lang' => 'fr', 'desc' => "Participant"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.PatientName.Label', 'lang' => 'it', 'desc' => "Partecipante"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.PatientName.Label', 'lang' => 'zh', 'desc' => "受测者"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Phone.Label', 'lang' => 'ca', 'desc' => "Telèfon"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Phone.Label', 'lang' => 'en', 'desc' => "Phone"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Phone.Label', 'lang' => 'es', 'desc' => "Teléfono"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Phone.Label', 'lang' => 'fr', 'desc' => "Téléphone"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Phone.Label', 'lang' => 'it', 'desc' => "Telefono"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Phone.Label', 'lang' => 'zh', 'desc' => "电话号码"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Program.Label', 'lang' => 'ca', 'desc' => "Codi programa"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Program.Label', 'lang' => 'en', 'desc' => "Program code"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Program.Label', 'lang' => 'es', 'desc' => "Código programa"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Program.Label', 'lang' => 'fr', 'desc' => "Code programme"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Program.Label', 'lang' => 'it', 'desc' => "Codice del programma"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Program.Label', 'lang' => 'zh', 'desc' => "项目编号"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Rounds.Label', 'lang' => 'ca', 'desc' => "Rondes totals"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Rounds.Label', 'lang' => 'en', 'desc' => "Total Rounds"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Rounds.Label', 'lang' => 'es', 'desc' => "Rondas totales"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Rounds.Label', 'lang' => 'fr', 'desc' => "Total de tours"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Rounds.Label', 'lang' => 'it', 'desc' => "Round totali"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Rounds.Label', 'lang' => 'zh', 'desc' => "总轮数"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Team.Label', 'lang' => 'ca', 'desc' => "Codi equip"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Team.Label', 'lang' => 'en', 'desc' => "Team code"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Team.Label', 'lang' => 'es', 'desc' => "Código equipo"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Team.Label', 'lang' => 'fr', 'desc' => "Code d'équipe"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Team.Label', 'lang' => 'it', 'desc' => "Codice team"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Team.Label', 'lang' => 'zh', 'desc' => "团队编号"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Title', 'lang' => 'ca', 'desc' => "IDENTIFICA AL PARTICIPANT"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Title', 'lang' => 'en', 'desc' => "IDENTIFY THE PARTICIPANT"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Title', 'lang' => 'es', 'desc' => "IDENTIFICAR PARTICIPANTE"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Title', 'lang' => 'fr', 'desc' => "IDENTIFIER LE PARTICIPANT"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Title', 'lang' => 'it', 'desc' => "IDENTIFICARE IL PARTECIPANTE"];
    $literals[] = ['group' => 'Web', 'key' => 'Prescription.Title', 'lang' => 'zh', 'desc' => "识别受测者"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Button.Verify', 'lang' => 'ca', 'desc' => "Verificar"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Button.Verify', 'lang' => 'en', 'desc' => "Verify"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Button.Verify', 'lang' => 'es', 'desc' => "Verificar"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Button.Verify', 'lang' => 'fr', 'desc' => "Vérifier"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Button.Verify', 'lang' => 'it', 'desc' => "Verificare"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Button.Verify', 'lang' => 'zh', 'desc' => "验证"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Title', 'lang' => 'ca', 'desc' => "Si us plau introdueixi el seu codi de test"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Title', 'lang' => 'en', 'desc' => "Please enter your test code"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Title', 'lang' => 'es', 'desc' => "Por favor ingrese su código de test"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Title', 'lang' => 'fr', 'desc' => "Veuillez entrer votre code de test"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Title', 'lang' => 'it', 'desc' => "Inserisci il tuo codice di prova"];
    $literals[] = ['group' => 'Web', 'key' => 'RequestQR.Title', 'lang' => 'zh', 'desc' => "请输入试剂上显示的代码"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Alert', 'lang' => 'ca',
            'desc' => "És obligatori proporcionar la vostra ubicació per continuar amb la verificació."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Alert', 'lang' => 'en',
            'desc' => "It is mandatory to provide your location in order to continue with the verification."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Alert', 'lang' => 'es',
            'desc' => "Es obligatorio proporcionar su ubicación para continuar con la verificación."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Alert', 'lang' => 'fr',
            'desc' => "Il est obligatoire de fournir votre emplacement afin de poursuivre la vérification."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Alert', 'lang' => 'it',
            'desc' => "È obbligatorio fornire la tua posizione per continuare con la verifica."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Button.Products', 'lang' => 'ca',
            'desc' => "Feu clic aquí per veure altres productes de Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Button.Products', 'lang' => 'en', 'desc' => "Click here to view other Linkcare products"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Button.Products', 'lang' => 'es',
            'desc' => "Haga clic aquí para ver otros productos Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Button.Products', 'lang' => 'fr',
            'desc' => "Cliquez ici pour voir d'autres produits Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Button.Products', 'lang' => 'it',
            'desc' => "Clicca qui per vedere altri prodotti Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Button.Products', 'lang' => 'zh', 'desc' => "单击此处查看其他 Linkcare 产品"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Error', 'lang' => 'ca',
            'desc' => "Aquest navegador no admet la geolocalització,  proveu-ho amb un altre."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Error', 'lang' => 'en',
            'desc' => "Geolocation is not supported by this browser, please try with a different one."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Error', 'lang' => 'es',
            'desc' => "La geolocalización no es compatible con este navegador, intente con uno diferente."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Error', 'lang' => 'fr',
            'desc' => "La géolocalisation n'est pas prise en charge par ce navigateur, veuillez essayer avec un autre."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Error', 'lang' => 'it',
            'desc' => "La geolocalizzazione non è supportata da questo browser, prova con un altro."];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.Company', 'lang' => 'ca', 'desc' => "FABRICANT"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.Company', 'lang' => 'en', 'desc' => "MANUFACTURER"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.Company', 'lang' => 'es', 'desc' => "FABRICANTE"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.Company', 'lang' => 'fr', 'desc' => "FABRICANT"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.Company', 'lang' => 'it', 'desc' => "PRODUTTORE"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.Company', 'lang' => 'zh', 'desc' => "制造商"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.RevisionDate', 'lang' => 'ca', 'desc' => "DATA DE REVISIÓ"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.RevisionDate', 'lang' => 'en', 'desc' => "REVISION DATE"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.RevisionDate', 'lang' => 'es', 'desc' => "FECHA DE REVISIÓN"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.RevisionDate', 'lang' => 'fr', 'desc' => "DATE DE RÉVISION"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.RevisionDate', 'lang' => 'it', 'desc' => "DATA DI REVISIONE"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Label.RevisionDate', 'lang' => 'zh', 'desc' => "修订日期"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Title', 'lang' => 'ca', 'desc' => "Test d'antígens verificat per Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Title', 'lang' => 'en', 'desc' => "Antigen Test Verified by Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Title', 'lang' => 'es', 'desc' => "Test de antígenos verificada por Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Title', 'lang' => 'fr', 'desc' => "Test d'antigène vérifié par Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Title', 'lang' => 'it', 'desc' => "Test antigenico verificato da Linkcare"];
    $literals[] = ['group' => 'Web', 'key' => 'Verification.Title', 'lang' => 'zh', 'desc' => "Linkcare 验证抗原测试"];
    return $literals;
}
?>