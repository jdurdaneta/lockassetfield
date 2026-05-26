<?php

/**
 * -------------------------------------------------------------------------
 * LockAssetField plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of LockAssetField.
 *
 * LockAssetField is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * LockAssetField is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LockAssetField. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by LockAssetField plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/lockassetfield
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Lockassetfield;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Clase de configuración principal del plugin LockAssetField.
 *
 * En GLPI 11:
 * - Los activos personalizados ya no se gestionan mediante GenericObject,
 *   sino mediante Asset Definitions del core.
 * - Este objeto centraliza la configuración general del plugin y el listado
 *   de tipos estándar soportados.
 */
class Config extends CommonDBTM
{
    /**
     * Nombre del derecho principal del plugin.
     *
     * @var string
     */
    public static $rightname = 'plugin_lockassetfield_config';

    /**
     * Habilita el histórico de cambios.
     *
     * @var bool
     */
    public $dohistory = true;

    /**
     * Derecho específico para permitir modificar campos bloqueados.
     *
     * @var int
     */
    public const RIGHT_UPDATE_FIELDS = 256;

    /**
     * Tabla principal de configuración.
     *
     * @var string
     */
    private static $table = 'glpi_plugin_lockassetfield_configs';

    /**
     * Devuelve el nombre localizado del tipo.
     *
     * @param int $nb Número de elementos.
     *
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return __('Bloqueo de campos', 'lockassetfield');
    }

    /**
     * Devuelve el nombre del menú.
     *
     * @return string
     */
    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    /**
     * Define el contenido del menú del plugin.
     *
     * @return array<string, mixed>
     */
    public static function getMenuContent(): array
    {
        return [
            'title'   => self::getTypeName(),
            'page'    => self::getSearchURL(false),
            'icon'    => self::getIcon(),
            'options' => [
                'lockassetfield' => [
                    'links' => [
                        'search' => self::getSearchURL(false),
                    ],
                ],
            ],
        ];
    }

    /**
     * Define las pestañas del objeto de configuración.
     *
     * @param array $options Opciones de contexto.
     *
     * @return array
     */
    public function defineTabs($options = []): array
    {
        $ong = [];

        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab(ConfigField::class, $ong, $options);
        $this->addStandardTab(ConfigAssetObject::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * Devuelve el nombre de las pestañas del objeto.
     *
     * @param CommonGLPI $item         Objeto GLPI.
     * @param int        $withtemplate Indica si se usa con plantillas.
     *
     * @return array|string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (get_class($item) === __CLASS__) {
            $tabs = [];

            $tabs[0] = __('General setup');
            $tabs[1] = __(ConfigField::getTypeName(), 'lockassetfield');
            $tabs[2] = __('Cambio de estado', 'lockassetfield');
            $tabs[3] = __(ConfigAssetObject::getTypeName(), 'lockassetfield');

            return $tabs;
        }

        return '';
    }

    /**
     * Muestra el contenido de la pestaña seleccionada.
     *
     * @param CommonGLPI $item         Objeto GLPI.
     * @param int        $tabnum       Número de pestaña.
     * @param int        $withtemplate Indica si se usa con plantillas.
     *
     * @return bool
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ): bool {
        switch ($tabnum) {
            case 0:
                $item->showForm(1);
                break;

            case 1:
                ConfigField::showConfigFieldForm();
                break;

            case 2:
                ConfigField::showConfigFieldStateForm();
                break;

            case 3:
                ConfigAssetObject::showConfigFieldForm();
                break;
        }

        return true;
    }

    /**
     * Opciones de búsqueda del objeto.
     *
     * @return array
     */
    public function rawSearchOptions(): array
    {
        $sopt = [];

        $sopt[] = [
            'id'   => 'common',
            'name' => __('Lock Asset Fields', 'lockassetfield'),
        ];

        $sopt[] = [
            'id'       => '1',
            'table'    => $this->getTable(),
            'field'    => 'name',
            'name'     => __('Field'),
            'datatype' => 'itemlink',
        ];

        $sopt[] = [
            'id'       => '2',
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active', 'glpi'),
            'datatype' => 'bool',
        ];

        $sopt[] = [
            'id'    => '3',
            'table' => $this->getTable(),
            'field' => 'comment',
            'name'  => __('Comment', 'glpi'),
        ];

        return $sopt;
    }

    /**
     * Devuelve los derechos asociados al plugin.
     *
     * @param string $interface Interfaz GLPI.
     *
     * @return array<int, string>
     */
    public function getRights($interface = 'central'): array
    {
        return [
            READ                      => __('Ver configuración', 'lockassetfield'),
            UPDATE                    => __('Actualizar configuración', 'lockassetfield'),
            self::RIGHT_UPDATE_FIELDS => __('Actualizar campos bloqueados', 'lockassetfield'),
        ];
    }

    /**
     * Devuelve el icono del plugin.
     *
     * @return string
     */
    public static function getIcon(): string
    {
        return 'ti ti-lock';
    }

    /**
     * Muestra el formulario principal de configuración.
     *
     * @param int   $id      Identificador del registro.
     * @param array $options Opciones del formulario.
     *
     * @return bool
     */
    public function showForm($id, $options = []): bool
    {
        if ($id > 0) {
            $this->getFromDB($id);
        } else {
            $this->getEmpty();
        }

        $options['candel'] = false;
        $options['canedit'] = self::canUpdate();
        $options['readonly'] = !self::canUpdate();

        $infoText = self::textLockAssetFieldInfo();

        $twig = TemplateRenderer::getInstance();
        $twig->display('@lockassetfield/config.html.twig', [
            'item'      => $this,
            'params'    => $options,
            'info_text' => $infoText,
        ]);

        return true;
    }

    /**
     * Texto informativo del plugin.
     *
     * @return string
     */
    private static function textLockAssetFieldInfo(): string
    {
        return "El plugin Bloqueo de campos de activos permite definir qué campos de los diferentes tipos de activos de GLPI deben permanecer bloqueados para evitar modificaciones no autorizadas.<br>
                Esto ayuda a mantener la integridad de los datos de inventario, como números de serie, inventarios, modelos o fabricantes.<br><br>

                Además, el plugin permite configurar bloqueos basados en el estado del activo. Si un activo se encuentra en uno de los estados seleccionados, su campo <strong>Estado</strong> quedará protegido contra cambios.<br><br>

                En GLPI 11, los activos personalizados definidos mediante <strong>Definiciones de activos</strong> pueden añadirse desde la pestaña <strong>Gestión de objetos</strong>, sustituyendo la antigua integración con GenericObject.<br><br>

                Utilice esta configuración para adaptar el control de edición de activos según las necesidades de su organización.";
    }

    /**
     * Instalación del objeto de configuración.
     *
     * @return void
     */
    public static function install(): void
    {
        global $DB;

        $table = getTableForItemType(__CLASS__);

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL DEFAULT '',
                `is_active` tinyint NOT NULL DEFAULT 0,
                `comment` text NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $config = new self();
            $config->add([
                'id'        => 1,
                'name'      => 'Bloqueo de campos',
                'is_active' => 1,
                'comment'   => '',
            ]);
        }
    }

    /**
     * Desinstalación del objeto de configuración.
     *
     * @return void
     */
    public static function uninstall(): void
    {
        global $DB;

        $table = getTableForItemType(__CLASS__);

        if ($DB->tableExists($table)) {
            $DB->dropTable($table);
        }
    }

    /**
     * Comprueba si el plugin está activo.
     *
     * @return bool
     */
    public static function isLockAssetFieldActive(): bool
    {
        global $DB;

        if (!$DB->tableExists(getTableForItemType(__CLASS__))) {
            return false;
        }

        $config = new self();

        if (!$config->getFromDB(1)) {
            return false;
        }

        return (bool) ($config->fields['is_active'] ?? false);
    }

    /**
     * Devuelve todos los tipos estándar soportados por el plugin.
     *
     * Importante:
     * - Esta función devuelve solo activos estándar.
     * - Los activos personalizados seleccionados se gestionan desde
     *   ConfigAssetObject y se mantienen en ConfigField.
     *
     * @return array<int, string>
     */
    public static function lockAssetFieldType(): array
    {
        return self::getStandardAssetTypes();
    }

    /**
     * Devuelve los tipos estándar soportados.
     *
     * @return array<int, string>
     */
    private static function getStandardAssetTypes(): array
    {
        return [
            'Computer',
            'Monitor',
            'NetworkEquipment',
            'Peripheral',
            'Printer',
            'ConsumableItem',
            'Phone',
            'Rack',
            'Enclosure',
            'PDU',
            'PassiveDCEquipment',
            'Cable',
            'SoftwareLicense',
            'Certificate',
        ];
    }
}