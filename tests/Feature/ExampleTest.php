<?php

test('redirects the root url to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
