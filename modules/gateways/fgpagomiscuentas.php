<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$fgpagomiscuentas_Description = "PagoMisCuentas";
$fgpagomiscuentas_Version = "1.0.0";

function fgpagomiscuentas_MetaData() {
    global $fgpagomiscuentas_Description;

    return array(
        "DisplayName" => $fgpagomiscuentas_Description,
        "APIVersion" => "1.1",
        "DisableLocalCreditCardInput" => false,
        "TokenisedStorage" => false
    );
}

function fgpagomiscuentas_config() {
    global $fgpagomiscuentas_Description, $fgpagomiscuentas_Version;

    return array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "PagoMisCuentas"
        ),

        "fgpmc_version" => array(
            "FriendlyName" => "Versi&oacute;n",
            "Type" => "dropdown",
            "Options" => array(
                "1" => "</option></select><script>$('[name=\"field[fgpmc_version]\"]').hide();</script><select style=\"display: none;\">"
            ),
            "Description" => "$fgpagomiscuentas_Description <strong>$fgpagomiscuentas_Version</strong>"
        ),

        "fgpmc_information" => array(
            "FriendlyName" => "<span style=\"color: red;\">Informaci&oacute;n</span>",
            "Type" => "dropdown",
            "Options" => array(
                "1" => "</option></select><script>$('[name=\"field[fgpmc_information]\"]').hide();</script><select style=\"display: none;\">"
            ),
            "Description" => "<strong>Para el funcionamiento correcto del m&oacute;dulo, deber&aacute; completar todos los campos marcados en <span style=\"color: red;\">color rojo</span>.</strong>"
        ),

        "fgpmc_codigoempresa" => array(
            "FriendlyName" => "<span style=\"color: red;\">C&oacute;digo de Empresa</span>",
            "Type" => "text",
            "Size" => "4",
            "Description" => "C&oacute;digo de empresa de empresa asignado por Prisma."
        ),

        "fgpmc_nroempresa" => array(
            "FriendlyName" => "<span style=\"color: red;\">N&uacute;mero de Empresa</span>",
            "Type" => "text",
            "Size" => "4",
            "Description" => "N&uacute;mero de empresa asignado por Prisma. Son los cuatro d&iacute;gitos que figuran en el mail de \"Confirmaci&oacute;n de aprobaci&oacute;n de solicitud\" que recibe la empresa."
        ),

        "fgpmc_clientid" => array(
            "FriendlyName" => "<span style=\"color: red;\">Identificaci&oacute;n del Cliente</span>",
            "Type" => "dropdown",
            "Options" => array(
                "client_id" => "ID Cliente"
            ),
            "Description" => "Opci&oacute;n escogida para identificar al cliente al completar el formulario de adhesi&oacute;n."
        ),

        "fgpmc_invoicehtml" => array(
            "FriendlyName" => "HTML Factura",
            "Type" => "textarea",
            "Rows" => "5",
            "Cols" => "60",
            "Description" => "C&oacute;digo HTML a mostrar dentro de la factura. Dejar en blanco para mostrar un bot&oacute;n con un <a href=\"https://paysrv2.pagomiscuentas.com/Inicio.html\">link</a> a su portal."
        ),

        "fgpmc_ftphost" => array(
            "FriendlyName" => "FTP: Url",
            "Type" => "text",
            "Size" => "25",
            "Description" => "Direcci&oacute;n para conectarse al FTP. Dejar en blanco para usar por defecto (ftps.pagomiscuentas.com)."
        ),

        "fgpmc_ftpport" => array(
            "FriendlyName" => "FTP: Puerto",
            "Type" => "text",
            "Size" => "4",
            "Description" => "Puerto mediante el cual se conecta al FTP. Dejar en blanco para usar por defecto (990)."
        ),

        "fgpmc_ftpuser" => array(
            "FriendlyName" => "<span style=\"color: red;\">FTP: Usuario</span>",
            "Type" => "text",
            "Size" => "8",
            "Description" => "Usuario para conectarse al FTP. Es el mismo con el que se ingresa al portal de PagoMisCuentas."
        ),

        "fgpmc_ftppass" => array(
            "FriendlyName" => "<span style=\"color: red;\">FTP: Contrase&ntilde;a</span>",
            "Type" => "password",
            "Size" => "30",
            "Description" => "Contrase&ntilde;a para conectarse al FTP. Es la misma con la que se ingresa al portal de PagoMisCuentas."
        )
    );
}

function fgpagomiscuentas_link($params) {
    if ($params['fgpmc_invoicehtml']) {
        return $params['fgpmc_invoicehtml'];
    }

    $html = "<a href=\"https://paysrv2.pagomiscuentas.com/Inicio.html\">Ir a PagoMisCuentas</a>";

    return $html;
}