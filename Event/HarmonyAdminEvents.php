<?php

namespace Harmony\Bundle\AdminBundle\Event;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class HarmonyAdminEvents
{

    // Events related to initialization

    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_INITIALIZE = 'harmony_admin.pre_initialize';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_INITIALIZE = 'harmony_admin.post_initialize';

    // Events related to backend views
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_DELETE = 'harmony_admin.pre_delete';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_DELETE = 'harmony_admin.post_delete';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_EDIT = 'harmony_admin.pre_edit';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_EDIT = 'harmony_admin.post_edit';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_LIST = 'harmony_admin.pre_list';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_LIST = 'harmony_admin.post_list';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_NEW = 'harmony_admin.pre_new';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_NEW = 'harmony_admin.post_new';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_SEARCH = 'harmony_admin.pre_search';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_SEARCH = 'harmony_admin.post_search';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_SHOW = 'harmony_admin.pre_show';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_SHOW = 'harmony_admin.post_show';

    // Events related to Doctrine entities
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_PERSIST = 'harmony_admin.pre_persist';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_PERSIST = 'harmony_admin.post_persist';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_UPDATE = 'harmony_admin.pre_update';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_UPDATE = 'harmony_admin.post_update';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const PRE_REMOVE = 'harmony_admin.pre_remove';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_REMOVE = 'harmony_admin.post_remove';

    // Events related to Doctrine Query Builder usage
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_LIST_QUERY_BUILDER = 'harmony_admin.post_list_query_builder';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    public const POST_SEARCH_QUERY_BUILDER = 'harmony_admin.post_search_query_builder';
}
