<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2013, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * ExpressionEngine Channel Parser Component (Custom Fields)
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Core
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class EE_Channel_custom_field_parser implements EE_Channel_parser_component {

	/**
	 * Check if custom fields are enabled.
	 *
	 * @param array		A list of "disabled" features
	 * @return Boolean	Is disabled?
	 */
	public function disabled(array $disabled, EE_Channel_preparser $pre)
	{
		return in_array('custom_fields', $disabled);
	}

	// ------------------------------------------------------------------------

	/**
	 * @todo Find all of the tags like the custom date fields?
	 *
	 * @param String	The tagdata to be parsed
	 * @param Object	The preparser object.
	 * @return Object	Channel fields api, to reduce a lookup (for now)
	 */
	public function pre_process($tagdata, EE_Channel_preparser $pre)
	{
		return ee()->api_channel_fields;
	}

	// ------------------------------------------------------------------------

	/**
	 * Replace all of the custom channel fields.
	 *
	 * @param String	The tagdata to be parsed
	 * @param Object	The channel parser object
	 * @param Mixed		The results from the preparse method
	 *
	 * @return String	The processed tagdata
	 */
	public function replace($tagdata, EE_Channel_data_parser $obj, $ft_api)
	{
		$tag = $obj->tag();
		$data = $obj->row();
		$prefix = $obj->prefix();

		$site_id = $data['site_id'];
		$cfields = $obj->channel()->cfields;
		$rfields = $obj->channel()->rfields;

		$rfields = isset($rfields[$site_id]) ? $rfields[$site_id] : array();
		$cfields = isset($cfields[$site_id]) ? $cfields[$site_id] : array();

		$cfields = array_diff_key($cfields, $rfields);

		if (empty($cfields))
		{
			return $tagdata;
		}

		$unprefixed_tag	= preg_replace('/^'.$prefix.'/', '', $tag);
		$field_name		= substr($unprefixed_tag.' ', 0, strpos($unprefixed_tag.' ', ' '));
		$param_string	= substr($unprefixed_tag.' ', strlen($field_name));

		$modifier = '';
		$modifier_loc = strpos($field_name, ':');

		if ($modifier_loc !== FALSE)
		{
			$modifier = substr($field_name, $modifier_loc + 1);
			$field_name = substr($field_name, 0, $modifier_loc);
		}

		if (isset($cfields[$field_name]))
		{
			$entry = '';
			$field_id = $cfields[$field_name];

			if (isset($data['field_id_'.$field_id]) && $data['field_id_'.$field_id] != '')
			{
				$params = array();
				$parse_fnc = ($modifier) ? 'replace_'.$modifier : 'replace_tag';

				if ($param_string)
				{
					$params = ee()->functions->assign_parameters($param_string);
				}

				$obj = $ft_api->setup_handler($field_id, TRUE);

				if ($obj)
				{
					$_ft_path = $ft_api->ft_paths[$ft_api->field_type];
					ee()->load->add_package_path($_ft_path, FALSE);

					$obj->_init(array('row' => $data));

					$data = $obj->pre_process($data['field_id_'.$field_id]);

					if (method_exists($obj, $parse_fnc))
					{
						$entry = $obj->$parse_fnc($data, $params, FALSE);
					}
					elseif (method_exists($obj, 'replace_tag_catchall'))
					{
						$entry = $obj->replace_tag_catchall($data, $params, FALSE, $modifier);
					}

					ee()->load->remove_package_path($_ft_path);
				}
				else
				{
					// Couldn't find a fieldtype
					$entry = ee()->typography->parse_type(
						ee()->functions->encode_ee_tags($data['field_id_'.$field_id]),
						array(
							'text_format'	=> $data['field_ft_'.$field_id],
							'html_format'	=> $data['channel_html_formatting'],
							'auto_links'	=> $data['channel_auto_link_urls'],
							'allow_img_url' => $data['channel_allow_img_urls']
						)
					);
				}

				// prevent accidental parsing of other channel variables in custom field data
				if (strpos($entry, '{') !== FALSE)
				{
					$entry = str_replace(
						array('{', '}'),
						array(unique_marker('channel_bracket_open'), unique_marker('channel_bracket_close')),
						$entry
					);
				}

				$tagdata = str_replace(LD.$tag.RD, $entry, $tagdata);
			}

			$tagdata = str_replace(LD.$tag.RD, '', $tagdata);
		}

		return $tagdata;
	}
}