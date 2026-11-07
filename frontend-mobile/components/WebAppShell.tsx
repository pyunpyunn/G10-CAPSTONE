import { ReactNode, useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import Ionicons from '@expo/vector-icons/Ionicons';
import ResQperationLogo from '@/components/ResQperationLogo';

const redPinPath =
  'M550 2C477 2 417 61 417 134C417 190 444 222 487 256C521 283 566 312 596 365C625 313 654 245 674 179C687 136 682 91 660 56C635 19 597 2 550 2Z M550 59C508 59 475 93 475 136C475 180 508 215 550 215C559 215 568 213 575 209L554 187L587 154L611 177C619 165 623 151 623 136C623 93 591 59 550 59Z';

export type WebAppShellNavItem = {
  key: string;
  title: string;
  icon: keyof typeof Ionicons.glyphMap;
  keywords?: string;
  onPress?: () => void;
};

export type WebAppShellNavGroup = {
  title: string;
  items: WebAppShellNavItem[];
};

type WebAppShellProps = {
  activeKey: string;
  pageTitle: string;
  dashboardItem: WebAppShellNavItem;
  groups: WebAppShellNavGroup[];
  userName: string;
  userRole: string;
  notificationCount?: number;
  onOpenNotifications?: () => void;
  onOpenProfile: () => void;
  onSelectKey: (key: string) => void;
  onLogout: () => void;
  refreshing?: boolean;
  onRefresh?: () => void;
  banner?: ReactNode;
  rightRail?: ReactNode;
  children: ReactNode;
};

function getInitials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  const initials = parts.map((part) => part[0]).join('').slice(0, 2).toUpperCase();
  return initials || 'RQ';
}

export function WebAppShell({
  activeKey,
  pageTitle,
  dashboardItem,
  groups,
  userName,
  userRole,
  notificationCount = 0,
  onOpenNotifications,
  onOpenProfile,
  onSelectKey,
  onLogout,
  refreshing = false,
  onRefresh,
  banner,
  rightRail,
  children,
}: WebAppShellProps) {
  const [isPinned, setIsPinned] = useState(false);
  const [isPeeking, setIsPeeking] = useState(false);
  const [isPinHovered, setIsPinHovered] = useState(false);
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
    const initial: Record<string, boolean> = {};
    groups.forEach((group) => {
      initial[group.title] = true;
    });
    return initial;
  });
  const [searchTerm, setSearchTerm] = useState('');
  const [isSearchOpen, setIsSearchOpen] = useState(false);

  const isExpanded = isPinned || isPeeking;
  const sidebarWidth = isExpanded ? 240 : 64;
  const initials = getInitials(userName);
  const unreadLabel = notificationCount > 99 ? '99+' : String(notificationCount);

  const allSearchItems: WebAppShellNavItem[] = [
    dashboardItem,
    ...groups.flatMap((g) => g.items),
  ];

  const query = searchTerm.trim().toLowerCase();
  const searchResults =
    query.length >= 1
      ? allSearchItems.filter((item) =>
          `${item.title} ${item.keywords || ''}`.toLowerCase().includes(query)
        )
      : [];

  function toggleGroup(title: string) {
    setOpenGroups((current) => ({
      ...current,
      [title]: !current[title],
    }));
  }

  function handleNavPress(item: WebAppShellNavItem) {
    if (item.onPress) {
      item.onPress();
      return;
    }
    onSelectKey(item.key);
  }

  return (
    <View style={styles.appShell}>
      {/* Left Sidebar (.leftbar from frontend-web/src/components/layout/Sidebar.jsx) */}
      <View
        style={[
          styles.leftbar,
          { width: sidebarWidth },
          !isPinned && isPeeking && styles.leftbarPeeking,
        ]}
        // @ts-ignore web pointer events for peek-on-hover
        onMouseEnter={() => setIsPeeking(true)}
        onMouseLeave={() => setIsPeeking(false)}
      >
        <View style={[styles.leftSidebarBrand, !isExpanded && styles.leftSidebarBrandCollapsed]}>
          {isExpanded ? (
            <ResQperationLogo width={204} height={42} />
          ) : (
            <Svg width={27} height={39} viewBox="415 0 274 368" accessibilityLabel="ResQperation">
              <Path fill="#c72e32" fillRule="evenodd" d={redPinPath} />
            </Svg>
          )}
        </View>

        <View
          style={[
            styles.leftSidebarActions,
            isExpanded && styles.leftSidebarActionsExpanded,
          ]}
        >
          <Pressable
            style={({ pressed }) => [
              styles.leftPinButton,
              (isPinned || isPinHovered) && styles.leftPinButtonActive,
              pressed && styles.leftPinButtonPressed,
            ]}
            onPress={() => setIsPinned((pinned) => !pinned)}
            // @ts-ignore web pointer events for hover state matching .left-pin-button:hover
            onMouseEnter={() => setIsPinHovered(true)}
            onMouseLeave={() => setIsPinHovered(false)}
            accessibilityRole="button"
            accessibilityLabel={isPinned ? 'Unpin left navigation' : 'Pin left navigation'}
          >
            {/* Exact Lucide Pin icon from frontend-web/src/components/layout/Sidebar.jsx */}
            <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
              <Path
                d="M12 17v5"
                stroke={isPinned || isPinHovered ? '#ffffff' : '#a0b4c8'}
                strokeWidth={1.9}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
              <Path
                d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"
                stroke={isPinned || isPinHovered ? '#ffffff' : '#a0b4c8'}
                strokeWidth={1.9}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </Svg>
          </Pressable>
        </View>

        <ScrollView style={styles.leftNav} contentContainerStyle={styles.leftNavContent}>
          {/* Top-level Dashboard Item */}
          <Pressable
            style={({ pressed }) => [
              styles.navItem,
              !isExpanded && styles.navItemCollapsed,
              activeKey === dashboardItem.key && styles.navItemActive,
              pressed && styles.navItemActive,
            ]}
            onPress={() => handleNavPress(dashboardItem)}
          >
            <View style={styles.navIconBox}>
              <Ionicons
                name={dashboardItem.icon}
                size={17}
                color={activeKey === dashboardItem.key ? '#ffffff' : '#e8eef5'}
              />
            </View>
            {isExpanded && (
              <Text
                style={[
                  styles.navItemText,
                  activeKey === dashboardItem.key && styles.navItemTextActive,
                ]}
                numberOfLines={1}
              >
                {dashboardItem.title}
              </Text>
            )}
          </Pressable>

          {/* Collapsible Navigation Groups (matching NavGroup in Sidebar.jsx) */}
          {groups.map((group) => {
            const isOpen = openGroups[group.title] !== false;
            return (
              <View key={group.title} style={styles.navGroup}>
                {isExpanded && (
                  <Pressable
                    style={styles.navSectionTitle}
                    onPress={() => toggleGroup(group.title)}
                  >
                    <Text style={styles.navSectionTitleText} numberOfLines={1}>
                      {group.title}
                    </Text>
                    <Ionicons
                      name={isOpen ? 'chevron-up' : 'chevron-down'}
                      size={13}
                      color="#a0b4c8"
                    />
                  </Pressable>
                )}

                {(isOpen || !isExpanded) && (
                  <View style={styles.navGroupItems}>
                    {group.items.map((item) => {
                      const isActive = activeKey === item.key;
                      return (
                        <Pressable
                          key={item.key}
                          style={({ pressed }) => [
                            styles.navItem,
                            !isExpanded && styles.navItemCollapsed,
                            isActive && styles.navItemActive,
                            pressed && styles.navItemActive,
                          ]}
                          onPress={() => handleNavPress(item)}
                        >
                          <View style={styles.navIconBox}>
                            <Ionicons
                              name={item.icon}
                              size={17}
                              color={isActive ? '#ffffff' : '#e8eef5'}
                            />
                          </View>
                          {isExpanded && (
                            <Text
                              style={[
                                styles.navItemText,
                                isActive && styles.navItemTextActive,
                              ]}
                              numberOfLines={1}
                            >
                              {item.title}
                            </Text>
                          )}
                        </Pressable>
                      );
                    })}
                  </View>
                )}
              </View>
            );
          })}
        </ScrollView>

        <View style={styles.leftSidebarLogout}>
          <Pressable
            style={({ pressed }) => [
              styles.navItem,
              !isExpanded && styles.navItemCollapsed,
              pressed && styles.navItemActive,
            ]}
            onPress={onLogout}
          >
            <View style={styles.navIconBox}>
              <Ionicons name="log-out-outline" size={17} color="#e8eef5" />
            </View>
            {isExpanded && (
              <Text style={styles.navItemText} numberOfLines={1}>
                Logout
              </Text>
            )}
          </Pressable>
        </View>
      </View>

      {/* Right Column: Topbar + Main Workspace */}
      <View style={[styles.mainColumn, { marginLeft: isPinned ? 240 : 64 }]}>
        {/* Topbar (.topbar from frontend-web/src/components/layout/Topbar.jsx) */}
        <View style={[styles.topbar, !isPinned && isPeeking && { marginLeft: 176 }]}>
          <View style={styles.topbarMainControls}>
            <Text style={styles.topbarPageTitle} numberOfLines={1}>
              {pageTitle}
            </Text>

            <View style={styles.topbarSearchControls}>
              <View style={styles.topbarSearchField}>
                <Ionicons name="search-outline" size={15} color="#a0b4c8" />
                <TextInput
                  style={styles.topbarSearchInput}
                  value={searchTerm}
                  placeholder="Search all modules"
                  placeholderTextColor="#a0b4c8"
                  onFocus={() => setIsSearchOpen(true)}
                  onBlur={() => setTimeout(() => setIsSearchOpen(false), 180)}
                  onChangeText={(text) => {
                    setSearchTerm(text);
                    setIsSearchOpen(true);
                  }}
                />
                {searchTerm.length > 0 && (
                  <Pressable
                    style={styles.topbarSearchClear}
                    onPress={() => setSearchTerm('')}
                  >
                    <Ionicons name="close" size={13} color="#a0b4c8" />
                  </Pressable>
                )}
              </View>

              <Pressable
                style={styles.headerAction}
                onPress={() => setIsSearchOpen((open) => !open)}
                accessibilityLabel="Filter search results"
              >
                <Ionicons name="funnel-outline" size={15} color="#e8eef5" />
              </Pressable>

              {isSearchOpen && searchTerm.trim().length > 0 && (
                <View style={styles.topbarSearchPopover}>
                  {searchResults.length === 0 ? (
                    <Text style={styles.topbarSearchEmpty}>No matching modules.</Text>
                  ) : (
                    searchResults.map((result) => (
                      <Pressable
                        key={result.key}
                        style={styles.topbarSearchResult}
                        onPress={() => {
                          setSearchTerm('');
                          setIsSearchOpen(false);
                          handleNavPress(result);
                        }}
                      >
                        <Ionicons name={result.icon} size={15} color="#1f3e5a" />
                        <View style={{ flex: 1 }}>
                          <Text style={styles.topbarSearchResultTitle}>{result.title}</Text>
                          <Text style={styles.topbarSearchResultSub}>Open module</Text>
                        </View>
                      </Pressable>
                    ))
                  )}
                </View>
              )}
            </View>
          </View>

          <View style={styles.headerActions}>
            {onOpenNotifications && (
              <Pressable
                style={styles.headerAction}
                onPress={onOpenNotifications}
                accessibilityLabel="Notifications"
              >
                <Ionicons name="notifications-outline" size={17} color="#e8eef5" />
                {notificationCount > 0 && (
                  <View style={styles.notificationBadge}>
                    <Text style={styles.notificationBadgeText}>{unreadLabel}</Text>
                  </View>
                )}
              </Pressable>
            )}

            <Pressable
              style={styles.headerProfile}
              onPress={onOpenProfile}
              accessibilityLabel="Profile"
            >
              <View style={styles.profileAvatar}>
                <Text style={styles.profileAvatarText}>{initials}</Text>
              </View>
              <View style={styles.profileCopy}>
                <Text style={styles.profileLabel} numberOfLines={1}>
                  {userName}
                </Text>
                <Text style={styles.profileRoleLabel} numberOfLines={1}>
                  {userRole}
                </Text>
              </View>
            </Pressable>
          </View>
        </View>

        {banner}

        {/* Main Workspace (.main / .page from layout.css) */}
        <View style={styles.mainBodyRow}>
          <ScrollView
            removeClippedSubviews={false}
            style={styles.mainScroll}
            contentContainerStyle={styles.pageContainer}
            refreshControl={
              onRefresh ? (
                <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
              ) : undefined
            }
          >
            {children}
          </ScrollView>

          {rightRail && <View style={styles.rightRail}>{rightRail}</View>}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  appShell: {
    flex: 1,
    flexDirection: 'row',
    width: '100%',
    height: '100%',
    backgroundColor: '#f2f4f7',
    position: 'relative',
  },
  leftbar: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: 0,
    zIndex: 60,
    backgroundColor: '#0f1c2d',
    borderRightWidth: 1,
    borderColor: '#2a4f72',
    flexDirection: 'column',
    overflow: 'hidden',
  },
  leftbarPeeking: {
    shadowColor: '#090f18',
    shadowOffset: { width: 12, height: 0 },
    shadowOpacity: 0.18,
    shadowRadius: 30,
    elevation: 16,
  },
  leftSidebarBrand: {
    height: 58,
    marginHorizontal: 12,
    marginBottom: 4,
    paddingHorizontal: 6,
    paddingVertical: 4,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#0f1c2d',
  },
  leftSidebarBrandCollapsed: {
    marginHorizontal: 8,
  },
  leftSidebarActions: {
    minHeight: 38,
    marginHorizontal: 14,
    marginTop: 8,
    marginBottom: 2,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  leftSidebarActionsExpanded: {
    justifyContent: 'flex-end',
  },
  leftPinButton: {
    width: 32,
    height: 32,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'transparent',
  },
  leftPinButtonActive: {
    borderColor: '#2a4f72',
    backgroundColor: '#1f3e5a',
    shadowColor: '#050a12',
    shadowOffset: { width: 0, height: 5 },
    shadowOpacity: 0.18,
    shadowRadius: 12,
    elevation: 4,
    transform: [{ translateY: -1 }],
  },
  leftPinButtonPressed: {
    transform: [{ translateY: 0 }],
    shadowOpacity: 0,
  },
  leftNav: {
    flex: 1,
    paddingHorizontal: 6,
  },
  leftNavContent: {
    paddingVertical: 4,
    paddingBottom: 14,
    gap: 2,
  },
  navGroup: {
    marginTop: 4,
    gap: 2,
  },
  navSectionTitle: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 30,
    marginHorizontal: 10,
    paddingHorizontal: 8,
    borderRadius: 10,
  },
  navSectionTitleText: {
    color: '#a0b4c8',
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
  },
  navGroupItems: {
    gap: 3,
  },
  navItem: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: 38,
    width: '100%',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: 'transparent',
    backgroundColor: 'transparent',
    paddingRight: 10,
  },
  navItemCollapsed: {
    justifyContent: 'center',
    paddingRight: 0,
  },
  navItemActive: {
    borderColor: '#2a4f72',
    backgroundColor: '#1f3e5a',
  },
  navIconBox: {
    width: 48,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
  },
  navItemText: {
    flex: 1,
    color: '#e8eef5',
    fontSize: 12,
    fontWeight: '600',
  },
  navItemTextActive: {
    color: '#ffffff',
    fontWeight: '700',
  },
  leftSidebarLogout: {
    paddingHorizontal: 6,
    paddingTop: 6,
    paddingBottom: 13,
    backgroundColor: '#0f1c2d',
    borderTopWidth: 1,
    borderColor: '#1a2f46',
  },
  mainColumn: {
    flex: 1,
    height: '100%',
    backgroundColor: '#f2f4f7',
    flexDirection: 'column',
  },
  topbar: {
    height: 64,
    backgroundColor: '#0f1c2d',
    borderBottomWidth: 1,
    borderColor: '#2a4f72',
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
    zIndex: 50,
  },
  topbarMainControls: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 20,
    minWidth: 0,
  },
  topbarPageTitle: {
    color: '#e8eef5',
    fontSize: 17,
    fontWeight: '700',
    maxWidth: 260,
  },
  topbarSearchControls: {
    flex: 1,
    maxWidth: 520,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    position: 'relative',
  },
  topbarSearchField: {
    flex: 1,
    maxWidth: 460,
    height: 36,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: '#2a4f72',
    borderRadius: 9,
    paddingHorizontal: 10,
    backgroundColor: '#0f1c2d',
  },
  topbarSearchInput: {
    flex: 1,
    height: '100%',
    color: '#e8eef5',
    fontSize: 12,
    outlineStyle: 'none' as any,
  },
  topbarSearchClear: {
    width: 22,
    height: 22,
    borderRadius: 5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  topbarSearchPopover: {
    position: 'absolute',
    top: 42,
    left: 0,
    right: 42,
    backgroundColor: '#ffffff',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#d0d9e4',
    padding: 6,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.16,
    shadowRadius: 20,
    elevation: 12,
    zIndex: 100,
    gap: 2,
  },
  topbarSearchEmpty: {
    color: '#3a4d61',
    fontSize: 12,
    padding: 10,
  },
  topbarSearchResult: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 8,
  },
  topbarSearchResultTitle: {
    color: '#0a1520',
    fontSize: 12,
    fontWeight: '700',
  },
  topbarSearchResultSub: {
    color: '#3a4d61',
    fontSize: 10.5,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  headerAction: {
    width: 34,
    height: 34,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#2a4f72',
    backgroundColor: '#0f1c2d',
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  notificationBadge: {
    position: 'absolute',
    top: -6,
    right: -6,
    minWidth: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: '#0f1c2d',
    backgroundColor: '#d71f28',
    paddingHorizontal: 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  notificationBadgeText: {
    color: '#ffffff',
    fontSize: 10,
    fontWeight: '700',
  },
  headerProfile: {
    height: 34,
    minWidth: 150,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingLeft: 6,
    paddingRight: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#2a4f72',
    backgroundColor: '#0f1c2d',
  },
  profileAvatar: {
    width: 27,
    height: 27,
    borderRadius: 13.5,
    backgroundColor: '#2a5a90',
    alignItems: 'center',
    justifyContent: 'center',
  },
  profileAvatarText: {
    color: '#ffffff',
    fontSize: 11,
    fontWeight: '700',
  },
  profileCopy: {
    gap: 1,
    maxWidth: 150,
  },
  profileLabel: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '700',
  },
  profileRoleLabel: {
    color: '#a0b4c8',
    fontSize: 10,
    fontWeight: '500',
  },
  mainBodyRow: {
    flex: 1,
    flexDirection: 'row',
    backgroundColor: '#f2f4f7',
  },
  mainScroll: {
    flex: 1,
  },
  pageContainer: {
    paddingHorizontal: 22,
    paddingTop: 26,
    paddingBottom: 104,
    maxWidth: 1080,
    width: '100%',
    alignSelf: 'center',
  },
  rightRail: {
    width: 300,
    borderLeftWidth: 1,
    borderColor: '#d0d9e4',
    backgroundColor: '#ffffff',
    padding: 16,
    gap: 16,
  },
});

