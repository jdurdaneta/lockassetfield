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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
use Glpi\Asset\AssetDefinition;
use GlpiPlugin\Lockassetfield\ConfigField;
use Plugin;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Clase de configuración principal del plugin LockAssetField.
 *
 * Esta clase:
 *  - Representa la tabla de configuración global del plugin.
 *  - Define derechos específicos (permisos) asociados al plugin.
 *  - Gestiona el menú y las pestañas de configuración.
 *  - Proporciona métodos auxiliares para comprobar si el plugin está activo
 *    y qué tipos de activos son gestionados para el bloqueo de campos.
 */
class Config extends CommonDBTM
{
    /** @var string Nombre del derecho de acceso principal para este objeto */
    public static $rightname = 'plugin_lockassetfield_config';

    /** @var bool Habilita el histórico de cambios del objeto */
    public $dohistory = true;

    /** @var int Valor del nuevo derecho específico para actualizar campos bloqueados */
    const RIGHT_UPDATE_FIELDS = 256;

    /** @var string Nombre de la tabla principal de configuración del plugin */
    private static $table = 'glpi_plugin_lockassetfield_configs';

    /**
     * Devuelve el nombre localizado del tipo de este objeto.
     *
     * Este nombre se utiliza en diferentes lugares de la interfaz
     * (búsquedas, menús, títulos, etc.).
     *
     * @param int $nb Número de elementos (no se utiliza en este caso).
     *
     * @return string Nombre localizado del tipo.
     */
    public static function getTypeName($nb = 0)
    {
        return __('Bloqueo de campos', 'lockassetfield');
    }

    /**
     * Devuelve el nombre del menú para este objeto.
     *
     * Este valor se usa para etiquetar la entrada del plugin en el menú
     * de configuración de GLPI.
     *
     * @return string Etiqueta (plural) para el menú.
     */
    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    /**
     * Define el contenido del menú del plugin.
     *
     * Estructura el menú que aparecerá en la interfaz de GLPI, incluyendo:
     *  - Título del menú.
     *  - Página principal (URL de búsqueda/listado).
     *  - Icono a mostrar.
     *  - Opciones adicionales (por ejemplo, enlaces de búsqueda).
     *
     * @return array Estructura del menú en el formato esperado por GLPI.
     */
    public static function getMenuContent()
    {
        $menu = [];
        $menu['title'] = self::getTypeName();
        // Página principal asociada al menú (búsqueda de objetos de configuración).
        $menu['page'] = self::getSearchURL(false);
        $menu['icon'] = self::getIcon();
        $menu['options'] = [
            'lockassetfield' => [
                // Enlaces estándar en el sub-menú (búsqueda, etc.).
                'links' => [
                    'search' => self::getSearchURL(false),
                ]
            ]
        ];
        return $menu;
    }

    /**
     * Define las pestañas (tabs) disponibles para este objeto.
     *
     * Se añaden:
     *  - Pestañas estándar de la propia clase.
     *  - Pestañas asociadas a la clase ConfigField.
     *  - Pestaña de histórico de cambios (Log).
     *
     * @param array $options Opciones de contexto para la configuración de pestañas.
     *
     * @return array Lista de pestañas definidas.
     */
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab(ConfigField::class, $ong, $options);
        // $this->addStandardTab(ConfigAssetObject::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * Devuelve el nombre de la pestaña para un item dado.
     *
     * Cuando el item es una instancia de esta clase (Config), se devuelven
     * las etiquetas para las diferentes pestañas:
     *  - Configuración general.
     *  - Configuración de campos.
     *  - Configuración de cambios de estado.
     *  - Configuración de objetos genéricos (si el plugin genericobject está activo).
     *
     * @param CommonGLPI $item         Objeto para el cual se solicitan las pestañas.
     * @param int        $withtemplate Indica si se usa con plantillas (no utilizado).
     *
     * @return array|string Array con nombres de pestañas o cadena vacía si no aplica.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (get_class($item) == __CLASS__) {
            $array_ret = [];
            $array_ret[0] = self::createTabEntry(__('General setup'), 0, null, 'ti ti-settings');
            $array_ret[1] = ConfigField::createTabEntry(ConfigField::getTypeName(), 0, null, 'ti ti-lock');
            $array_ret[2] = \State::createTabEntry(__('Cambio de estado', 'lockassetfield'));
            $array_ret[3] = AssetDefinition::createTabEntry(AssetDefinition::getTypeName(2));

            return $array_ret;
        }
        return '';
    }

    /**
     * Muestra el contenido de la pestaña seleccionada para un item.
     *
     * Según el número de pestaña:
     *  - 0: muestra el formulario principal de configuración general.
     *  - 1: muestra la configuración de campos bloqueados.
     *  - 2: muestra la configuración de bloqueo por cambio de estado.
     *  - 3: muestra la configuración asociada a GenericObject (si está activo).
     *
     * @param CommonGLPI $item         Objeto para el cual se muestra el contenido.
     * @param int        $tabnum       Número de la pestaña seleccionada.
     * @param int        $withtemplate Indica si se usa con plantillas (no utilizado).
     *
     * @return bool True si el contenido se ha mostrado correctamente.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;

        switch ($tabnum) {
            case 0:
                // Formulario de configuración general.
                $item->showForm(1);
                break;

            case 1:
                // Formulario de configuración de campos bloqueados.
                ConfigField::showConfigFieldForm();
                break;

            case 2:
                // Formulario de configuración de bloqueo por estado.
                ConfigField::showConfigFieldStateForm();
                break;

            case 3:
                ConfigAssetDefinition::showConfigFieldForm();
                break;
        }

        return true;
    }

    /**
     * Define las opciones de búsqueda para este objeto.
     *
     * Estas opciones se utilizan en el buscador avanzado de GLPI e indican
     * qué campos pueden usarse como criterios de búsqueda, su tipo de dato,
     * y la forma en que se presentan al usuario.
     *
     * @return array Opciones de búsqueda disponibles para este objeto.
     */
    public function rawSearchOptions()
    {
        $sopt = [];

        // Grupo común de opciones de búsqueda.
        $sopt[] = [
            'id'   => 'common',
            'name' => __('Lock Asset Fields', 'lockassetfield'),
        ];

        // Campo "name" (nombre de la configuración).
        $sopt[] = [
            'id'       => '1',
            'table'    => $this->getTable(),
            'field'    => 'name',
            'name'     => __('Field'),
            'datatype' => 'itemlink',
        ];

        // Campo "is_active" (activo / inactivo).
        $sopt[] = [
            'id'       => '2',
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active', 'glpi'),
            'datatype' => 'bool',
        ];

        // Campo "comment" (comentario libre).
        $sopt[] = [
            'id'       => '3',
            'table'    => $this->getTable(),
            'field'    => 'comment',
            'name'     => __('Comment', 'glpi'),
            // datatype por defecto (texto).
        ];

        return $sopt;
    }

    /**
     * Devuelve la lista de derechos (permisos) gestionados por esta clase.
     *
     * Se definen:
     *  - READ: Ver la configuración del plugin.
     *  - UPDATE: Actualizar la configuración global.
     *  - RIGHT_UPDATE_FIELDS: Actualizar/gestionar los campos bloqueados.
     *
     * @param string $interface Interfaz desde la que se consultan los derechos (central, helpdesk, etc.).
     *
     * @return array Lista de derechos con su etiqueta localizada.
     */
    function getRights($interface = 'central')
    {
        // Definición de derechos específicos del plugin.
        $rights = [
            READ                  => __('Ver configuración', 'lockassetfield'),
            UPDATE                => __('Actualizar configuración', 'lockassetfield'),
            self::RIGHT_UPDATE_FIELDS => __("Actualizar campos bloqueados", "lockassetfield")
        ];

        return $rights;
    }

    /**
     * Devuelve el identificador del icono usado para representar el plugin.
     *
     * Normalmente es una clase CSS correspondiente a un icono de la librería
     * utilizada por GLPI (por ejemplo Tabler Icons).
     *
     * @return string Identificador del icono.
     */
    public static function getIcon()
    {
        return 'ti ti-lock';
    }

    /**
     * Muestra el formulario principal de configuración del plugin.
     *
     * Según el ID:
     *  - Si $id > 0, carga el registro correspondiente desde la base de datos.
     *  - Si $id == 0, prepara un objeto "vacío".
     *
     * Posteriormente:
     *  - Configura opciones de permisos (sin borrado, editable según derechos).
     *  - Obtiene el texto informativo del plugin.
     *  - Renderiza la plantilla Twig asociada a la configuración.
     *
     * @param int   $id      Identificador del registro de configuración.
     * @param array $options Opciones adicionales para el formulario.
     *
     * @return bool True si el formulario se ha mostrado correctamente.
     */
    public function showForm($id, $options = [])
    {
        if ($id > 0) {
            $this->getFromDB($id);
        } else {
            $this->getEmpty();
        }

        $options['candel']   = false;
        $options['canedit']  = self::canUpdate();
        $options['readonly'] = !self::canUpdate();

        // Texto explicativo sobre el funcionamiento del plugin.
        $info_text = self::textLockAssetFiledInfo();

        $twig = TemplateRenderer::getInstance();
        $twig->display('@lockassetfield/config.html.twig', [
            'item'      => $this,
            'params'    => $options,
            'info_text' => $info_text,
        ]);

        return true;
    }

    /**
     * Texto informativo del plugin.
     *
     * Devuelve una descripción HTML del propósito y funcionamiento
     * del plugin, para mostrarse en la interfaz de configuración.
     *
     * @return string Texto descriptivo en formato HTML.
     */
    private static function textLockAssetFiledInfo()
    {
        return "El plugin Bloqueo de campos de activos permite definir qué campos de los diferentes tipos de activos de GLPI deben permanecer bloqueados para evitar modificaciones no autorizadas.<br>
                Esto ayuda a mantener la integridad de los datos de inventario, como números de serie, inventarios, modelos o fabricantes.<br><br>

                Además, el plugin permite configurar bloqueos basados en el estado del activo. Si un activo se encuentra en uno de los estados seleccionados, su campo <strong>Estado</strong> quedará protegido contra cambios.<br><br>

                En el caso de activos creados mediante el plugin <strong>GenericObject</strong>, primero deben activarse en la pestaña <strong>Gestión de objetos</strong> para que aparezcan en las secciones <em>Bloqueo de campos</em> y <em>Cambio de estado</em>.<br><br>

                Utilice esta configuración para adaptar el control de edición de activos según las necesidades de su organización.";
    }

    /**
     * Realiza el proceso de instalación a nivel de base de datos.
     *
     * Crea la tabla de configuración si no existe y añade un registro
     * inicial con la configuración por defecto (id = 1):
     *  - Nombre genérico.
     *  - Plugin activo.
     *  - Comentario vacío.
     *
     * @return void
     */
    public static function install()
    {
        /** @var DBmysql $DB */
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
     * Realiza el proceso de desinstalación a nivel de base de datos.
     *
     * Elimina la tabla de configuración asociada a esta clase.
     *
     * @return void
     */
    public static function uninstall()
    {
        global $DB;
        $DB->dropTable(getTableForItemType(__CLASS__));
    }

    /**
     * Comprueba si la configuración de bloqueo de campos está activa.
     *
     * Consulta el registro de configuración principal (id = 1)
     * y devuelve el valor del campo `is_active`.
     *
     * @return bool True si la funcionalidad está activa, false en caso contrario.
     */
    public static function isLockAssetFieldActive(): bool
    {
        $config = new self();
        $config->getFromDB(1);

        return $config->fields['is_active'];
    }

    /**
     * Devuelve la lista de tipos de activos gestionados por el plugin.
     *
     * Estos itemtypes son los que se usarán para aplicar las reglas
     * de bloqueo de campos en la configuración y en los hooks.
     *
     * @return array Lista de nombres de tipos de activos (itemtypes).
     */
    public static function lockAssetFieldType()
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
            'Certificate'
        ];
    }
}
