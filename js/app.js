'use strict';

/**
 * Videoquanda ideally needs to be rewritten.
 * Preferably in React, along with some Jasmine specs.
 * As part of which, the circular reference between videoPlayer and videoQuanda would be removed.
 */

import videoPlayer from './video';
import videoQuanda from './videoquanda';

videoPlayer.videoQuanda = videoQuanda;
videoQuanda.videoPlayer = videoPlayer;
