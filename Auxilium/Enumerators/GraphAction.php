<?php

namespace Auxilium\Enumerators;

enum GraphAction: string
{
    case DELETE_CONFIRM = "@delete_confirm";
    case DELETE = "@delete";
    case EDIT = "@edit";
    case UNLINK = "@unlink";
    case NEW_PROPERTY = "@new_property";
    case SEARCH = "@search";
    case REFERENCES = "@references";
    case REF_ERROR = "@ref_error";
    case VIEW = "@view";
    case PDF = "@pdf";
}
