import { useEffect, useState, type ReactNode } from 'react';
import { View } from 'react-native';

export function MobileTabPanels<T extends string>({ activeKey, renderPanel }: {
  activeKey: T;
  renderPanel: (key: T) => ReactNode;
}) {
  const [visited, setVisited] = useState<T[]>([activeKey]);
  const panels = visited.includes(activeKey) ? visited : [...visited, activeKey];
  useEffect(() => {
    setVisited(current => current.includes(activeKey) ? current : [...current, activeKey]);
  }, [activeKey]);

  return <>{panels.map(key => <View key={key} style={key === activeKey ? undefined : { display: 'none' }}
    accessibilityElementsHidden={key !== activeKey} importantForAccessibility={key === activeKey ? 'auto' : 'no-hide-descendants'}>
    {renderPanel(key)}
  </View>)}</>;
}
