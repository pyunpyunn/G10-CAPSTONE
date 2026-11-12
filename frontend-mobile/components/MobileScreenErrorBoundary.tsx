import { Component, Fragment, type ErrorInfo, type ReactNode } from 'react';
import { Pressable, Text, View } from 'react-native';
import { palette, radius, spacing } from '@/constants/resqTheme';

type Props = { children: ReactNode };
type State = { error: Error | null; attempt: number };

export class MobileScreenErrorBoundary extends Component<Props, State> {
  state: State = { error: null, attempt: 0 };

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('A mobile screen failed to render.', error, info.componentStack);
  }

  render() {
    if (this.state.error) {
      return (
        <View style={{ gap: spacing.md, padding: spacing.lg, borderRadius: radius.md, backgroundColor: palette.card }}>
          <Text style={{ color: palette.unsafe, fontSize: 16, fontWeight: '800' }}>This screen could not be displayed.</Text>
          <Text style={{ color: palette.textSoft }}>Your rescue tracking remains active. Retry the screen or switch tabs.</Text>
          <Pressable
            accessibilityRole="button"
            style={{ minHeight: 44, alignItems: 'center', justifyContent: 'center', borderRadius: radius.md, backgroundColor: palette.navActive }}
            onPress={() => this.setState(state => ({ error: null, attempt: state.attempt + 1 }))}
          >
            <Text style={{ color: '#fff', fontWeight: '800' }}>Retry screen</Text>
          </Pressable>
        </View>
      );
    }

    return <Fragment key={this.state.attempt}>{this.props.children}</Fragment>;
  }
}
