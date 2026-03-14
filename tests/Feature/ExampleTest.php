<?php

test('home redirects to cameron', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/cameron');
});
