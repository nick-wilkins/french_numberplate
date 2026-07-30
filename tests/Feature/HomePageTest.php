<?php

use App\Support\PlateCatalogue;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

test('the home page renders the heading and byline', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Welcome to the French Number Plate Game')
        ->assertSee('Chesterton Wilkins');
});

test('the home page is a livewire component', function () {
    Livewire::test('pages::home')
        ->assertSee('Welcome to the French Number Plate Game')
        ->assertSee('Chesterton Wilkins');
});

test('every plate in the catalogue is rendered on the board', function () {
    $response = $this->get(route('home'))->assertOk();

    foreach (PlateCatalogue::all() as $plate) {
        $response->assertSee('data-code="'.$plate['code'].'"', escape: false);
    }
});

test('the board renders one tappable plate per code', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, 'data-code="'))->toBe(PlateCatalogue::count());
});

test('both cars are offered before the game starts', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Which car are you in?')
        ->assertSee('Blue car')
        ->assertSee('Red car');
});

test('the board is broken into decade groups so a child can find a number', function () {
    $response = $this->get(route('home'))->assertOk();

    foreach (PlateCatalogue::metropolitanDecades() as $decade) {
        $response->assertSee($decade['label']);
    }
});

test('the page explains how to play without leaving the board', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('How to play')
        ->assertSee('tap that square', escape: false)
        ->assertSee('Press and hold')
        ->assertSee('you both found it', escape: false);
});

test('filters are described in plain language for children', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('the ones you have splatted')
        ->assertSee('the ones either car has splatted')
        ->assertSee('the ones nobody has spotted yet');
});

test('the page advertises itself as an installable app', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('/manifest.json', escape: false)
        ->assertSee('apple-mobile-web-app-capable', escape: false);
});

test('the web manifest and service worker are published', function () {
    expect(public_path('manifest.json'))->toBeFile()
        ->and(public_path('sw.js'))->toBeFile()
        ->and(public_path('icons/icon-192.png'))->toBeFile()
        ->and(public_path('icons/icon-512.png'))->toBeFile()
        ->and(public_path('icons/maskable-512.png'))->toBeFile();
});

test('the application exposes no authentication routes', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn (RegisteredRoute $route): string => $route->uri())
        ->all();

    expect($uris)
        ->not->toContain('login')
        ->not->toContain('register')
        ->not->toContain('logout')
        ->not->toContain('forgot-password')
        ->not->toContain('dashboard');
});
