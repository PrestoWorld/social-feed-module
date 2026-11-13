import React from 'react';
import SocialFeedScreen from './components/pages/SocialFeedScreen';

const w = window as any;
w.__moduleScreens = w.__moduleScreens || {};
w.__moduleScreens['social-feed'] = SocialFeedScreen;

export default SocialFeedScreen;
export { SocialFeedScreen };