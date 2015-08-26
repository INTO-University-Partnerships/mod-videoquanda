'use strict';

import * as lib from '../lib';

describe('lib', () => {
    describe('lineBreakToTag', () => {
        it('should replace Windows line terminators with html tags', () => {
            expect(lib.lineBreakToTag('foo\r\nbar\r\nwibble\r\n\r\n\r\nfinish')).toBe('foo<br/>bar<br/>wibble<br/><br/><br/>finish');
        });

        it('should replace Linux line terminators with html tags', () => {
            expect(lib.lineBreakToTag('foo\nbar\nwibble\n\n\nfinish')).toBe('foo<br/>bar<br/>wibble<br/><br/><br/>finish');
        });

        it('should replace carriage returns with html tags', () => {
            expect(lib.lineBreakToTag('foo\rbar\rwibble\r\r\rfinish')).toBe('foo<br/>bar<br/>wibble<br/><br/><br/>finish');
        });

        it('should not alter a string that has no line terminators', () => {
            expect(lib.lineBreakToTag('Mike McGowan')).toBe('Mike McGowan');
        });
    });

    describe('tagToLineBreak', () => {
        it('should replace a self-closing tag with a Windows line terminator', () => {
            expect(lib.tagToLineBreak('<br/>')).toBe('\r\n');
        });

        it('should replace a non-self-closing tag with a Windows line terminator', () => {
            expect(lib.tagToLineBreak('<br>')).toBe('\r\n');
        });

        it('should replace multiple self-closing tag with Windows line terminators', () => {
            expect(lib.tagToLineBreak('<br/><br/><br/>')).toBe('\r\n\r\n\r\n');
        });

        it('should replace uppercase tags with Windows line terminators', () => {
            expect(lib.tagToLineBreak('<BR/>')).toBe('\r\n');
        });

        it('should not alter a string that has no "br" tags', () => {
            expect(lib.tagToLineBreak('Mike McGowan')).toBe('Mike McGowan');
        });
    });

    describe('imgTagToSrcAttr', () => {
        it('should replace a self-closing "img" tag with the contents of its "src" attribute', () => {
            expect(lib.imgTagToSrcAttr('<div>foo</div><img src="http://example.com/a.png"/><div>bar</div>'))
                .toBe('<div>foo</div>http://example.com/a.png<div>bar</div>');
        });

        it('should replace an uppercase self-closing "img" tag with the contents of its "src" attribute', () => {
            expect(lib.imgTagToSrcAttr('<div>foo</div><IMG src="http://example.com/a.png"/><div>bar</div>'))
                .toBe('<div>foo</div>http://example.com/a.png<div>bar</div>');
        });

        it('should replace a non-self-closing "img" tag with the contents of its "src" attribute', () => {
            expect(lib.imgTagToSrcAttr('<div>foo</div><img src="http://example.com/a.png"><div>bar</div>'))
                .toBe('<div>foo</div>http://example.com/a.png<div>bar</div>');
        });

        it('should replace two self-closing "img" tags with the contents of their "src" attribute', () => {
            expect(lib.imgTagToSrcAttr('<div>foo</div><img src="http://example.com/a.png"/><img src="http://foo.com/bar.png"/><div>bar</div>'))
                .toBe('<div>foo</div>http://example.com/a.pnghttp://foo.com/bar.png<div>bar</div>');
        });

        it('should replace a self-closing "img" tag (that has additional attributes) with the contents of its "src" attribute', () => {
            expect(lib.imgTagToSrcAttr('<div>foo</div><img class="some-class" src="http://foo.com/bar.png" title="foo"/><div>bar</div>'))
                .toBe('<div>foo</div>http://foo.com/bar.png<div>bar</div>');
        });

        it('should not alter a string that has no "img" tags', () => {
            expect(lib.imgTagToSrcAttr('Mike McGowan')).toBe('Mike McGowan');
        });
    });

    describe('removeAllTags', () => {
        it('should remove all self-closing and non-self-closing tags, regardless of case', () => {
            expect(lib.removeAllTags('<div>foo</div><SPAN>bar</SPAN><br/>wibble<br><img src="http://example.com/a.png"/><script src="http://example.com/thing.js"></script><button>Press Me</button>'))
                .toBe('foobarwibblePress Me');
        });

        it('should not alter a string that has no "img" tags', () => {
            expect(lib.removeAllTags('Mike McGowan')).toBe('Mike McGowan');
        });
    });
});
