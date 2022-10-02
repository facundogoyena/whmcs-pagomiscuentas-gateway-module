# whmcs-pagomiscuentas-gateway-module
Pago Mis Cuentas WHMCS Payment Gateway Module

## Configuracion
- Para la configuracion hay que hablar con la gente de Pago Mis Cuentas y hacer varias pruebas.

## Instalacion
- Configurar una tarea programada (cron job) que ejecute el archivo fgpagomiscuentas.php?do=upload dentro de la carpeta callback.
- Configurar una tarea programada (cron job) que ejecute el archivo fgpagomiscuentas.php?do=download dentro de la carpeta callback.

## Dependencias
- WHMCS Argentina Holidays Cron (https://github.com/facundogoyena/whmcs-argentina-holidays-cron)
