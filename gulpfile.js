'use strict';

var source = require('vinyl-source-stream'),
    del = require('del'),
    gulp = require('gulp'),
    gutil = require('gulp-util'),
    gulpif = require('gulp-if'),
    notify = require('gulp-notify'),
    plumber = require('gulp-plumber'),
    uglify = require('gulp-uglify'),
    streamify = require('gulp-streamify'),
    eslint = require('gulp-eslint'),
    browserify = require('browserify'),
    babelify = require('babelify'),
    watchify = require('watchify'),
    envify = require('envify/custom');

function handleErrors() {
    var args = Array.prototype.slice.call(arguments);
    notify.onError({
        title: 'Compile Error',
        message: '<%= error.message %>'
    }).apply(this, args);
}

function rebundle(bundler, production) {
    var stream = bundler.bundle();
    return stream
        .on('error', handleErrors)
        .pipe(plumber())
        .pipe(gulpif(!production, source('videoquanda.js')))
        .pipe(gulpif(production, source('videoquanda.min.js')))
        .pipe(gulpif(production, streamify(uglify())))
        .pipe(gulp.dest('./static/js/build/'));
}

function buildScript(production) {
    process.env.NODE_ENV = production ? 'production' : 'development';

    var entries = ['./js/app.js'];
    var props = {
        entries: entries,
        debug: false,
        cache: {},
        packageCache: {}
    };

    var bundler = production ? browserify(props) : watchify(browserify(props));
    bundler.transform(babelify).transform(envify());
    bundler.on('update', function () {
        rebundle(bundler, production);
        gutil.log('Rebundle ...');
    });

    return rebundle(bundler, production);
}

gulp.task('clean', function () {
    del('./static/js/build/');
});

gulp.task('lint', function () {
    return gulp.src(['./js/**/*.js'])
        .pipe(eslint())
        .pipe(eslint.format());
});

gulp.task('build', function () {
    return buildScript(true);
});

gulp.task('watch', function () {
    return buildScript(false);
});
