<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_menus
 *
 * @copyright   (C) 2011 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Menus\Administrator\Table;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Menu;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Menu table
 *
 * @since  1.6
 */
class MenuTable extends Menu
{
    /**
     * Method to delete a node and, optionally, its child nodes from the table.
     *
     * @param   integer  $pk        The primary key of the node to delete.
     * @param   boolean  $children  True to delete child nodes, false to move them up a level.
     *
     * @return  boolean  True on success.
     *
     * @since   2.5
     */
    public function delete($pk = null, $children = false)
    {
        $return = parent::delete($pk, $children);

        if ($return) {
            /** @TODO Improve this when the database driver support the JSON data type.
             *  Delete menu item id from the #__modules.menu_assignment assigned array
             */
            $db    = $this->getDbo();
            $query = $db->createQuery()
                ->select($db->quoteName(['id', 'menu_assignment']))
                ->from($db->quoteName('#__modules'))
                ->where(
                    [
                        $db->quoteName('client_id') . ' = 0',
                        $db->quoteName('menu_assignment') . ' IS NOT NULL',
                    ]
                );

            $iterator = $db->setQuery($query)->getIterator() ;

            if (\count($iterator)) {
                foreach ($iterator as $row) {
                    $moduleAssignment = json_decode($row->menu_assignment);
                    $currentPages     = $moduleAssignment->assigned ?? [];

                    if (\count($currentPages)) {
                        $newPages = array_filter($currentPages, function ($itemId) use ($pk) {
                            return $pk != $itemId;
                        });

                        // Update only if the module was assigned to the menu item
                        if (\count($currentPages) != \count($newPages)) {
                            $moduleAssignment->assigned = $newPages;

                            $row->menu_assignment = json_encode($moduleAssignment);

                            $db->updateObject('#__modules', $row, ['id']);
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Overloaded check function
     *
     * @return  boolean  True on success, false on failure
     *
     * @see     \Joomla\CMS\Table\Table::check
     * @since   4.0.0
     */
    public function check()
    {
        $return = parent::check();

        if ($return) {
            // Set publish_up to null date if not set
            if (!$this->publish_up) {
                $this->publish_up = null;
            }

            // Set publish_down to null date if not set
            if (!$this->publish_down) {
                $this->publish_down = null;
            }

            // Check the publish down date is not earlier than publish up.
            if (!\is_null($this->publish_down) && !\is_null($this->publish_up) && $this->publish_down < $this->publish_up) {
                $this->setError(Text::_('JGLOBAL_START_PUBLISH_AFTER_FINISH'));

                return false;
            }

            if ((int) $this->home) {
                // Set the publish down/up always for home.
                $this->publish_up   = null;
                $this->publish_down = null;
            }
        }

        return $return;
    }
}
