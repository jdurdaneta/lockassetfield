<?php

use Glpi\Asset\AssetDefinition;
use GlpiPlugin\Lockassetfield\Config;
use GlpiPlugin\Lockassetfield\ConfigField;
use GlpiPlugin\Lockassetfield\Profile;

/**
 * Instalación del plugin.
 *
 * @return bool
 */
function plugin_lockassetfield_install(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_LOCKASSETFIELD_MIN_GLPI, '<')) {
        \Session::addMessageAfterRedirect(
            __('This plugin requires GLPI >= 11.0.0', 'lockassetfield'),
            false,
            ERROR
        );

        return false;
    }

    Config::install();
    ConfigField::install();

    // Sincroniza los tipos estándar soportados y migra, si procede,
    // itemtypes heredados de GenericObject a los nuevos custom assets de GLPI 11.
    ConfigField::syncSupportedAssetTypes();

    return true;
}

/**
 * Desinstalación del plugin.
 *
 * @return bool
 */
function plugin_lockassetfield_uninstall(): bool
{
    global $DB;

    ConfigField::uninstall();
    Config::uninstall();
    Profile::uninstall();

    foreach (Profile::getAllRights() as $right) {
        \ProfileRight::deleteProfileRights([$right['field']]);
    }

    $DB->delete(
        'glpi_logs',
        [
            'itemtype' => Config::class,
        ]
    );

    return true;
}

/**
 * Hook previo al borrado o purgado de una definición de activo.
 *
 * Limpia la configuración del plugin para evitar que un Asset Definition
 * eliminado siga apareciendo como Glpi\CustomAsset\... en las pestañas:
 * - Bloqueo de campos
 * - Cambio de estado
 *
 * @param \CommonDBTM $item Item eliminado o purgado.
 *
 * @return bool
 */
function plugin_lockassetfield_pre_asset_definition_delete($item): bool
{
    if (!$item instanceof AssetDefinition) {
        return true;
    }

    plugin_lockassetfield_delete_config_for_asset_definition($item);

    return true;
}

/**
 * Elimina de glpi_plugin_lockassetfield_configfields la configuración asociada
 * a una definición de activo personalizada.
 *
 * @param AssetDefinition $definition Definición de activo.
 *
 * @return void
 */
function plugin_lockassetfield_delete_config_for_asset_definition(AssetDefinition $definition): void
{
    global $DB;

    $table = getTableForItemType(ConfigField::class);

    if (!$DB->tableExists($table)) {
        return;
    }

    $itemtypes = plugin_lockassetfield_get_asset_definition_itemtype_candidates($definition);

    foreach ($itemtypes as $itemtype) {
        $DB->delete(
            $table,
            [
                'itemtype' => $itemtype,
            ]
        );
    }
}

/**
 * Devuelve posibles itemtypes asociados a una definición de activo.
 *
 * Se usan varios candidatos para cubrir diferencias de mayúsculas/minúsculas
 * o de generación del nombre de clase:
 * - Glpi\CustomAsset\proyectorAsset
 * - Glpi\CustomAsset\ProyectorAsset
 * - Glpi\CustomAsset\proyector_assetAsset
 * - Glpi\CustomAsset\ProyectorAsset
 *
 * @param AssetDefinition $definition Definición de activo.
 *
 * @return array<int, string>
 */
function plugin_lockassetfield_get_asset_definition_itemtype_candidates(AssetDefinition $definition): array
{
    $itemtypes = [];

    if (method_exists($definition, 'getAssetClassName')) {
        $assetClassName = $definition->getAssetClassName();

        if (!empty($assetClassName)) {
            $itemtypes[] = (string) $assetClassName;
        }
    }

    $names = [];

    foreach (['system_name', 'name'] as $field) {
        if (!empty($definition->fields[$field])) {
            $names[] = (string) $definition->fields[$field];
        }
    }

    foreach ($names as $name) {
        $name = trim($name);

        if ($name === '') {
            continue;
        }

        $camelized = str_replace('_', '', ucwords($name, '_'));

        $itemtypes[] = 'Glpi\\CustomAsset\\' . $name . 'Asset';
        $itemtypes[] = 'Glpi\\CustomAsset\\' . ucfirst($name) . 'Asset';
        $itemtypes[] = 'Glpi\\CustomAsset\\' . lcfirst($camelized) . 'Asset';
        $itemtypes[] = 'Glpi\\CustomAsset\\' . $camelized . 'Asset';
    }

    return array_values(array_unique($itemtypes));
}

/**
 * Hook previo a actualización.
 *
 * @param \CommonDBTM $item Item actualizado.
 *
 * @return bool
 */
function plugin_lockassetfield_pre_item_update($item): bool
{
    if (
        \Session::haveRight('config', UPDATE)
        || \Session::haveRight(Config::$rightname, Config::RIGHT_UPDATE_FIELDS)
    ) {
        return true;
    }

    $input = &$item->input;

    if (!isset($input['id'])) {
        return true;
    }

    $itemtype = get_class($item);
    $lockedFields = ConfigField::getLockedFieldsForItemType($itemtype);

    $stateId = $item->fields['states_id'] ?? null;
    if ($stateId !== null) {
        $lockedFieldState = ConfigField::isFieldStateLocked($itemtype, $stateId);
        if ($lockedFieldState) {
            $lockedFields = array_merge($lockedFields, $lockedFieldState);
        }
    }

    if (empty($lockedFields)) {
        return true;
    }

    $current = new $itemtype();

    if ($current->getFromDB($input['id'])) {
        $changed = false;

        foreach ($lockedFields as $field) {
            if (
                isset($input[$field])
                && array_key_exists($field, $current->fields)
                && $input[$field] != $current->fields[$field]
            ) {
                $input[$field] = $current->fields[$field];
                $changed = true;

                $fieldLabel = ConfigField::getLockfieldFieldLabel($field);

                \Session::addMessageAfterRedirect(
                    sprintf(
                        __('El campo "%s" no puede ser modificado', 'lockassetfield'),
                        $fieldLabel
                    ),
                    false,
                    WARNING
                );
            }
        }

        if ($changed) {
            $item->input = $input;
        }
    }

    return true;
}

/**
 * Hook posterior a mostrar item.
 *
 * @param array $params Parámetros del hook.
 *
 * @return bool
 */
function plugin_lockassetfield_post_show_item(array $params): bool
{
    $item = $params['item'] ?? null;

    if (!$item instanceof \CommonDBTM) {
        return true;
    }

    $itemtype = $item->getType();

    if (!ConfigField::existInConfigField($itemtype)) {
        return true;
    }

    if (
        \Session::haveRight('config', UPDATE)
        || \Session::haveRight(Config::$rightname, Config::RIGHT_UPDATE_FIELDS)
    ) {
        return true;
    }

    $lockedFields = ConfigField::getLockedFieldsForItemType($itemtype);

    $stateId = $item->fields['states_id'] ?? null;
    if ($stateId !== null) {
        $lockedFieldState = ConfigField::isFieldStateLocked($itemtype, $stateId);
        if ($lockedFieldState) {
            $lockedFields = array_merge($lockedFields, $lockedFieldState);
        }
    }

    if (empty($lockedFields)) {
        return true;
    }

    echo "<script type='text/javascript'>
        $(document).ready(function() {";

    foreach ($lockedFields as $field) {
        $tooltip = __('Este campo está bloqueado por configuración', 'lockassetfield');

        // Escapado mínimo para evitar romper el JavaScript si el nombre del campo
        // o el texto traducido contiene comillas.
        $fieldJs = addslashes($field);
        $tooltipJs = addslashes($tooltip);

        echo "
            $('input[name=\"{$fieldJs}\"], select[name=\"{$fieldJs}\"], textarea[name=\"{$fieldJs}\"]')
                .prop('readonly', true)
                .prop('disabled', true)
                .css({
                    'background-color': '#f5f5f5',
                    'cursor': 'not-allowed',
                    'opacity': '0.6'
                })
                .attr('title', '{$tooltipJs}');
        ";
    }

    echo "
        });
    </script>";

    return true;
}