'use strict';

const del = require('del');
const gulp = require('gulp');
const gulpif = require('gulp-if');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');

const bundle = [
    {
        'source': 'node_modules/leaflet/dist/**',
        'dest': 'asset/vendor/leaflet',
    },
    // A small patch allows to manage permissions.
    // {
    //     'source': 'node_modules/leaflet-draw/dist/**',
    //     'dest': 'asset/vendor/leaflet-draw',
    // },
    {
        'source': 'node_modules/leaflet-fullscreen/dist/**',
        'dest': 'asset/vendor/leaflet-fullscreen',
    },
    {
        'source': 'node_modules/leaflet-geosearch/dist/**',
        'dest': 'asset/vendor/leaflet-geosearch',
    },
    {
        'source': 'node_modules/leaflet-geosearch/assets/css/**',
        'dest': 'asset/vendor/leaflet-geosearch',
    },
    // TODO The original has a bug not yet fixed.
    // {
    //     'source': 'node_modules/leaflet-groupedlayercontrol/dist/**',
    //     'dest': 'asset/vendor/leaflet-groupedlayercontrol',
    // },
    {
        'source': 'node_modules/leaflet.markercluster/dist/**',
        'dest': 'asset/vendor/leaflet-markercluster',
    },
    // Upgraded, but not yet pushed upstream and not in npm (on leaflet only).
    // {
    //     'source': 'node_modules/leaflet-paste/**',
    //     'dest': 'asset/vendor/leaflet-paste',
    // },
    {
        'source': 'node_modules/leaflet-providers/leaflet-providers.js',
        'dest': 'asset/vendor/leaflet-providers',
        'rename': true,
        'uglify': true,
    },
    // Currently customized directly.
    // {
    //     'source': 'node_modules/leaflet-styleeditor/dist/**',
    //     'dest': 'asset/vendor/leaflet-styleeditor',
    // },
    {
        'source': 'node_modules/leaflet-tilelayer-wmts/dist/leaflet-tilelayer-wmts.js',
        'dest': 'asset/vendor/leaflet-tilelayer-wmts',
        'rename': true,
    },
];

const hack_leaflet_draw = function (done) {
    gulp.src('asset/vendor/leaflet-draw/leaflet.draw-src.js')
        .pipe(uglify())
        .pipe(rename('leaflet.draw.js'))
        .pipe(gulp.dest('asset/vendor/leaflet-draw'));
    done();
};

gulp.task('clean', function(done) {
    bundle.forEach(function (module) {
        return del.sync(module.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    bundle.forEach(function (module) {
        gulp.src(module.source)
            .pipe(gulpif(module.rename, rename({suffix:'.min'})))
            .pipe(gulpif(module.uglify, uglify()))
            .pipe(gulp.dest(module.dest));
    });
    done();
});

gulp.task('default', gulp.series('clean', 'sync', hack_leaflet_draw));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
