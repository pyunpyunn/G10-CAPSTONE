import type { ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { HouseholdButton, HouseholdEmpty, HouseholdLoading } from '@/components/household/HouseholdUI';
import { LoadingState } from '@/components/rescuer/RescuerUI';
import { palette, radius, spacing } from '@/constants/resqTheme';

type MobileDataStateProps = {
  isInitialLoading?: boolean;
  error?: string;
  loadingLabel?: string;
  onRetry?: () => void;
  children: ReactNode;
};

export function MobileDataState({
  isInitialLoading = false,
  error = '',
  loadingLabel = 'Loading...',
  onRetry,
  children,
}: MobileDataStateProps) {
  if (isInitialLoading) {
    return <HouseholdLoading label={loadingLabel} />;
  }

  if (error) {
    return (
      <View style={styles.errorWrap}>
        <HouseholdEmpty icon="cloud-offline-outline" title="Data unavailable" body={error} />
        {onRetry ? (
          <HouseholdButton label="Try again" icon="refresh-outline" tone="light" onPress={onRetry} />
        ) : null}
      </View>
    );
  }

  return children;
}

type RescuerDataStateProps = {
  isInitialLoading?: boolean;
  error?: string;
  loadingLabel?: string;
  onRetry?: () => void;
  children: ReactNode;
};

export function RescuerDataState({
  isInitialLoading = false,
  error = '',
  loadingLabel = 'Loading...',
  onRetry,
  children,
}: RescuerDataStateProps) {
  if (isInitialLoading) {
    return <LoadingState label={loadingLabel} />;
  }

  if (error) {
    return (
      <View style={styles.errorWrap}>
        <Text style={styles.errorTitle}>Data unavailable</Text>
        <Text style={styles.errorBody}>{error}</Text>
        {onRetry ? (
          <Pressable style={styles.retryButton} onPress={onRetry}>
            <Text style={styles.retryText}>Try again</Text>
          </Pressable>
        ) : null}
      </View>
    );
  }

  return children;
}

const styles = StyleSheet.create({
  errorWrap: {
    gap: spacing.md,
    paddingVertical: spacing.lg,
  },
  errorTitle: {
    color: palette.text,
    fontSize: 16,
    fontWeight: '900',
  },
  errorBody: {
    color: palette.textSoft,
    fontSize: 13,
    lineHeight: 20,
    fontWeight: '600',
  },
  retryButton: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: '#fff',
  },
  retryText: {
    color: palette.nav,
    fontSize: 13,
    fontWeight: '800',
  },
});
