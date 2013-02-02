<?php


/**
 * About this snippet
 * 
 * This file contains an isolated method originally intended to be used in:
 * 		/cms/system/module/pages/model/page_m.php 
 * 		version 2.2 Beta 1
 * 
 * Read the description below to see what it does.
 * Screenshot of example raw output included 
 * 
 * 
 * It was not developed further because the relationship field type is going to be disabled for pages:   
 * 		https://github.com/pyrocms/pyrocms/pull/2296
 * 
 * It may be of some use as example during the implementation of:
 * 		https://github.com/pyrocms/pyrocms/issues/2283
 * 
 * Also, depending on how the page field type turns out it may still be applied with only minor differences.
 * 
 */

	/**
     * Get the pages - if any - that are related to the active page
     * 
     * This method first performs a simple check whether any page types have a relationship field connected to the page type of this page  
     * Then it returns the pages - if any - from the page types that are related to this page 
     * 
     * The first two queries could easily be combined into one complex query. Using two queries is a design choice to efficiently handle most pages with one very basic query. 
     * 
     * @param int $page_id The id of the page
     * @param int $stream_id The stream id of the page's page_type.
     *
     * @return mixed An array with mixed data if there are related pages. Returns empty on a missing stream_id, page_id or lack of results results
     */


    public function get_related_pages($page_id = false, $stream_id = false)
    {
        // Can't do anything without a stream id or page id
        if( ! $stream_id || ! $page_id) 
        {
            return;
        }
        
        // Get all relationship fields in the pages namespace
        // Store some data for later use
        $fields = $this->db
            ->select('id, field_name, field_slug, field_data')
            ->from('data_fields')
            ->where('field_type', 'relationship')
            ->where('field_namespace', 'pages')
            ->get()->result();
        
        // We know there is no relationship possible if there are no such fields at all
        if (empty($fields) || ! is_array($fields)) 
        {
            return; 
        }
        
        $matching_fields = array();
        $field_slugs = array();
        $field_names = array();
        
        // Check whether there are fields matching to the stream of this page's page type
        // Store the field ids, names and slugs of matching fields for later use
        foreach ($fields as $field) 
        {
            $field->field_data = unserialize($field->field_data);
            
            // Make sure the field data is good to go - errors do happen on occasion
            if ( ! isset($field->field_data['choose_stream']))
            {
                continue;
            }
            
            // Move to the next one if there is no match
            if ( $field->field_data['choose_stream'] !== $stream_id) 
            {
                continue; 
            }
            
            $matching_fields[] = $field->id;
            $field_slugs[$field->id] = $field->field_slug;
            $field_names[$field->id] = $field->field_name;
        }
        
        // We can return here if there are no matching fields
        if (empty($matching_fields)) 
        {
            return;
        }

        // Find out whether the streams containing the matching fields are in use
        // And select bits of useful data for the next step
        $active_relations = $this->db
            ->select('page_types.id as type_id, page_types.slug as type_slug, page_types.title as type_title, page_types.stream_id, data_streams.stream_slug, data_streams.stream_prefix, data_field_assignments.field_id')
            ->from('data_field_assignments')
            ->join('page_types', 'data_field_assignments.stream_id = page_types.stream_id', 'inner')
            ->join('data_streams', 'data_streams.id = page_types.stream_id', 'inner')
            ->where_in('data_field_assignments.field_id', $matching_fields)
            ->where('data_streams.stream_namespace', 'pages')
            ->get()->result();
        
        // We know enough if there were no active relationship fields
        if (empty($active_relations) || ! is_array($active_relations)) 
        {
            return;
        }

        $i = 0;
        $related_page_sets = array();
        
        // Go through the active relationships to find out the related pages 
        // Store the results grouped by page type and relationship field combination
        foreach ($active_relations as $ar) 
        {
            $related_pages = $this->db
                ->select('pages.*')
                ->from($ar->stream_prefix.$ar->stream_slug)
                ->join('pages', 'pages.entry_id = '.$ar->stream_prefix.$ar->stream_slug.'.id', 'inner')
                ->where($ar->stream_prefix.$ar->stream_slug.'.'.$field_slugs[$ar->field_id].' = '.$page_id)
                ->where('pages.type_id = '. $ar->type_id)
                ->get()->result();
            
            // Move on to the next one if there are no related pages here
            if (empty($related_pages) || ! is_array($related_pages)) 
            {
                continue;
            }
            
            // Store the related pages (and all other info we got) grouped by page type & relationship field combination
            $related_page_sets[$i] = $ar;
            $related_page_sets[$i]->field_name = $field_names[$ar->field_id];
            $related_page_sets[$i]->field_slug = $field_slugs[$ar->field_id];
            $related_page_sets[$i]->related_pages = $related_pages;
            $i++;
        }
        
        // Last chance to return empty
        if (empty($related_page_sets)) 
        {
            return;
        }
        
        return $related_page_sets;
    } 
