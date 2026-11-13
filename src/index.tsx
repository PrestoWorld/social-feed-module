import React from 'react';
import SocialFeedScreen from './components/pages/SocialFeedScreen';
import { registerModuleMenu, createMenuSection, createMenuGroup, createMenuItem } from '@prestoworld/menu-registry';

const w = window as any;
w.__moduleScreens = w.__moduleScreens || {};
w.__moduleScreens['social-feed'] = SocialFeedScreen;

// Register SocialFeed menus for the Seller Center dashboard
registerModuleMenu({
  sections: [
    createMenuSection({
      id: 'content',
      title: 'Nội Dung & Sách',
      priority: 75,
      groups: [
        createMenuGroup({
          id: 'social-feed',
          label: 'Social Feed',
          icon: 'MessageSquare',
          screenId: 'social-feed',
          priority: 76,
          items: [
            createMenuItem({
              id: 'social-feed',
              screenId: 'social-feed',
              label: 'Social Feed',
              icon: 'MessageSquare',
              url: '#/social-feed',
              priority: 76,
            }),
          ],
        }),
      ],
    }),
  ],
});

export default SocialFeedScreen;
export { SocialFeedScreen };