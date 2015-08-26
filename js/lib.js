'use strict';

/**
 * @returns {number} (current timestamp in seconds)
 */
export function time() {
    return Math.round(new Date() / 1e3);
}

/**
 * convert line breaks to a <br/> tag
 * @param {string} s
 * @returns {string}
 */
export function lineBreakToTag(s) {
    return s.replace(/(\r\n|\n\r|\r|\n)/g, '<br/>');
}

/**
 * convert <br/> tags to a line break
 * @param {string} s
 * @returns {string}
 */
export function tagToLineBreak(s) {
    return s.replace(/<br\/?>/ig, '\r\n');
}

/**
 * extract 'src' attribute from 'img' tag
 * @param {string} s
 * @returns {string}
 */
export function imgTagToSrcAttr(s) {
    return s.replace(/<img([^>]+)src="(.*?)"([^>]*)>/ig, '$2');
}

/**
 * removes all tags
 * @param {string} s
 * @returns {string}
 */
export function removeAllTags(s) {
    return s.replace(/(<([^>]+)>)/ig, '');
}
