<?php  namespace Mitch\LaravelDoctrine\Extensions\Sortable;


interface Sortable {

    public function getPosition();
    public function setPosition($position);
}