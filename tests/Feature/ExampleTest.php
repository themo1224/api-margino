<?php

it('returns a successful health check', function () {
    $this->get('/up')->assertOk();
});
