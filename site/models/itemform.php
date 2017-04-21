<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Tjucm
 * @author     Parth Lawate <contact@techjoomla.com>
 * @copyright  2016 Techjoomla
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.modelform');
jimport('joomla.event.dispatcher');

require_once JPATH_SITE . "/components/com_tjfields/filterFields.php";
require_once JPATH_ADMINISTRATOR . '/components/com_tjucm/classes/funlist.php';

use Joomla\Utilities\ArrayHelper;
/**
 * Tjucm model.
 *
 * @since  1.6
 */
class TjucmModelItemForm extends JModelForm
{
	private $item = null;

	/**
	 * @var      string    The prefix to use with controller messages.
	 * @since    1.6
	 */
	protected $text_prefix = 'COM_TJUCM';

	/**
	 * @var   	string  	Alias to manage history control
	 * @since   3.2
	 */
	public $typeAlias = 'com_tjucm.item';

	private $client = '';

	// Use imported Trait in model
	use TjfieldsFilterField;

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see        JController
	 * @since      1.6
	 */
	public function __construct($config = array())
	{
		$this->common  = new TjucmFunList;

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @return void
	 *
	 * @since  1.6
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication('com_tjucm');

		// Load state from the request userState on edit or from the passed variable on default
		if (JFactory::getApplication()->input->get('layout') == 'edit')
		{
			$id = JFactory::getApplication()->getUserState('com_tjucm.edit.item.id');
		}
		else
		{
			$id = JFactory::getApplication()->input->get('id');
			JFactory::getApplication()->setUserState('com_tjucm.edit.item.id', $id);
		}

		$this->setState('item.id', $id);

		// Get item type
		$ucmType = $app->getUserStateFromRequest('com_tjucm.itemform.client', 'client');

		if (!empty($ucmType))
		{
			$this->setState('item.ucmType', $ucmType);
		}

		// Load the parameters.
		$params       = $app->getParams();
		$params_array = $params->toArray();

		if (isset($params_array['item_id']))
		{
			$this->setState('item.id', $params_array['item_id']);
		}

		$this->setState('params', $params);
	}

	/**
	 * Method to get an ojbect.
	 *
	 * @param   integer  $id  The id of the object to get.
	 *
	 * @return Object|boolean Object on success, false on failure.
	 *
	 * @throws Exception
	 */
	public function &getData($id = null)
	{
		if ($this->item === null)
		{
			$this->item = false;

			if (empty($id))
			{
				$id = $this->getState('item.id');
			}

			// Get a level row instance.
			$table = $this->getTable();

			// Attempt to load the row.
			if ($table !== false && $table->load($id))
			{
				// Convert the JTable to a clean JObject.
				$properties  = $table->getProperties(1);
				$this->item = ArrayHelper::toObject($properties, 'JObject');
			}

			// Get ucm type
			$ucmType  = $this->getState('item.ucmType');

			// Get UCM type id from uniquue identifier
			JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tjucm/models');
			$tjUcmModelType = JModelLegacy::getInstance('Type', 'TjucmModel');
			$typeId = $tjUcmModelType->getTypeId($ucmType);

			// Get ucm type table
			$typeTable = $this->getTable($type = 'Type', $prefix = 'TjucmTable', $config = array());
			$typeTable->load($typeId);

			$this->item->params = clone $this->getState('params');

			// Compute view access permissions.
			if ($access = $this->getState('filter.access'))
			{
				// If the access filter has been set, we already know this user can view.
				$this->item->params->set('access-view', true);
			}
			else
			{
				// If no access filter is set, the layout takes some responsibility for display of limited information.
				$user = JFactory::getUser();
				$groups = $user->getAuthorisedViewLevels();

				if (in_array($typeTable->access, $groups))
				{
					$this->item->params->set('access-view', true);
				}
				else
				{
					$this->item->params->set('access-view', false);
				}
			}
		}

		return $this->item;
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return    JTable    A database object
	 *
	 * @since    1.6
	 */
	public function getTable($type = 'Item', $prefix = 'TjucmTable', $config = array())
	{
		$this->addTablePath(JPATH_ADMINISTRATOR . '/components/com_tjucm/tables');

		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Get an item by alias
	 *
	 * @param   string  $alias  Alias string
	 *
	 * @return int Element id
	 */
	public function getItemIdByAlias($alias)
	{
		$table = $this->getTable();

		$table->load(array('alias' => $alias));

		return $table->id;
	}

	/**
	 * Method to check in an item.
	 *
	 * @param   integer  $id  The id of the row to check out.
	 *
	 * @return  boolean True on success, false on failure.
	 *
	 * @since    1.6
	 */
	public function checkin($id = null)
	{
		// Get the id.
		$id = (!empty($id)) ? $id : (int) $this->getState('item.id');

		if ($id)
		{
			// Initialise the table
			$table = $this->getTable();

			// Attempt to check the row in.
			if (method_exists($table, 'checkin'))
			{
				if (!$table->checkin($id))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Method to check out an item for editing.
	 *
	 * @param   integer  $id  The id of the row to check out.
	 *
	 * @return  boolean True on success, false on failure.
	 *
	 * @since    1.6
	 */
	public function checkout($id = null)
	{
		// Get the user id.
		$id = (!empty($id)) ? $id : (int) $this->getState('item.id');

		if ($id)
		{
			// Initialise the table
			$table = $this->getTable();

			// Get the current user object.
			$user = JFactory::getUser();

			// Attempt to check the row out.
			if (method_exists($table, 'checkout'))
			{
				if (!$table->checkout($user->get('id'), $id))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get an array of data items
	 *
	 * @param   string  $client  client value
	 *
	 * @return mixed Array of data items on success, false on failure.
	 */
	public function setClient($client)
	{
		$this->client = $client;
	}

	/**
	 * Get an client value
	 *
	 * @return mixed Array of data items on success, false on failure.
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  JForm  A JForm object on success, false on failure
	 *
	 * @since    1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$app = JFactory::getApplication();

		// Get the form.
		$form = $this->loadForm(
			'com_tjucm.itemform', 'itemform',
			array('control' => 'jform',
				'load_data' => $loadData
			)
		);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return   mixed  The data for the form.
	 *
	 * @since    1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_tjucm.edit.item.data', array());

		if (empty($data))
		{
			if ($this->item === null)
			{
				$this->item = $this->getItem();
			}

			$data = $this->item;
		}

		return $data;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data              The form data.
	 * @param   array  $extra_jform_data  Exra field data.
	 * @param   array  $post              all form field data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	public function save($data, $extra_jform_data = '', $post = '')
	{
		$id    = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('item.id');
		$state = (!empty($data['state'])) ? 1 : 0;
		$user  = JFactory::getUser();
		$status_title = JFactory::getApplication()->input->get('form_status');

		if ($id)
		{
			// Check the user can edit this item
			$authorised = $user->authorise('core.edit', 'com_tjucm.item.' . $id) || $authorised = $user->authorise('core.edit.own', 'com_tjucm.item.' . $id);
		}
		else
		{
			// Check the user can create new items in this section
			$authorised = $user->authorise('core.create', 'com_tjucm');
		}

		if ($authorised !== true)
		{
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$data['type_id'] = $this->common->getDataValues('#__tj_ucm_types', 'id AS type_id', 'unique_identifier = "' . $this->client . '"', 'loadResult');

		$table = $this->getTable();

		if ($id == 0)
		{
			$data['state'] = 0;
		}

		if ($table->save($data) === true)
		{
			$id = (int) $this->getState($this->getName() . '.id');

			if (!empty($extra_jform_data))
			{
				$data_extra = array();
				$data_extra['content_id'] = $table->id;
				$data_extra['client'] = $this->client;
				$data_extra['fieldsvalue'] = $extra_jform_data;

				// Save extra fields data.
				$this->saveExtraFields($data_extra);
			}

			return $table->id;
		}
		else
		{
			throw new Exception($table->getError());
		}
	}

	/**
	 * Method to duplicate an Item
	 *
	 * @param   array  &$pks  An array of primary key IDs.
	 *
	 * @return  boolean  True if successful.
	 *
	 * @throws  Exception
	 */
	public function duplicate(&$pks)
	{
		$user = JFactory::getUser();

		// Access checks.
		if (!$user->authorise('core.create', 'com_tjucm'))
		{
			throw new Exception(JText::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
		}

		$dispatcher = JEventDispatcher::getInstance();
		$context    = $this->option . '.' . $this->name;

		// Include the plugins for the save events.
		JPluginHelper::importPlugin($this->events_map['save']);

		$table = $this->getTable();

		foreach ($pks as $pk)
		{
			if ($table->load($pk, true))
			{
				// Reset the id to create a new record.
				$table->id = 0;

				if (!$table->check())
				{
					throw new Exception($table->getError());
				}

				if (!empty($table->type_id))
				{
					if (is_array($table->type_id))
					{
						$table->type_id = implode(',', $table->type_id);
					}
				}
				else
				{
					$table->type_id = '';
				}

				// Trigger the before save event.
				$result = $dispatcher->trigger($this->event_before_save, array($context, &$table, true));

				if (in_array(false, $result, true) || !$table->store())
				{
					throw new Exception($table->getError());
				}

				// Trigger the after save event.
				$dispatcher->trigger($this->event_after_save, array($context, &$table, true));
			}
			else
			{
				throw new Exception($table->getError());
			}
		}

		// Clean cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Method to delete one or more records.
	 *
	 * @param   array  &$ids  An array of record primary keys.
	 *
	 * @return  boolean  True if successful, false if an error occurs.
	 *
	 * @since   12.2
	 */
	/*public function delete(&$ids)
	{
		foreach ($ids as $id)
		{
			if (parent::delete($id))
			{
				$this->deleteExtraFieldsData($id[0], $this->client);
			}
		}
	}*/

	/**
	 * Method to delete data
	 *
	 * @param   array  $data  Data to be deleted
	 *
	 * @return bool|int If success returns the id of the deleted item, if not false
	 *
	 * @throws Exception
	 */
	public function delete($data)
	{
		$id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('item.id');

		$table = $this->getTable();

		if ($table->delete($data['id']) === true)
		{
			$this->deleteExtraFieldsData($data['id'], $data['client']);

			return $id;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Check if data can be saved
	 *
	 * @return bool
	 */
	public function getCanSave()
	{
		$table = $this->getTable();

		return $table !== false;
	}

	/**
	 * Method to getAliasFieldNameByView
	 *
	 * @param   array  $view  An array of record primary keys.
	 *
	 * @return  boolean  True if successful, false if an error occurs.
	 *
	 * @since   12.2
	 */
	public function getAliasFieldNameByView($view)
	{
		switch ($view)
		{
			case 'type':
			case 'typeform':
				return 'alias';
			break;
		}
	}
}
