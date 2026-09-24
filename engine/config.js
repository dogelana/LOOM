// @loom-file release=0.15.39 revision=54 policy=package-priority
window.LoomConfig = Object.freeze({
  engineVersion: '0.15.39',
  discoveryIntervalMs: 4000,
  heartbeatIntervalMs: 5000,
  heartbeatLeaseMs: 90000,
  heartbeatStaleMs: 90000,
  heartbeatActivityMinMs: 1000,
  heartbeatRetryMs: 2500,
  sessionRefreshMs: 1800,
  telemetryRefreshMs: 900,
  cacheBust: { enabled:true, staticVersion:'0.15.39', assetStrategy:'content-hash', moduleStrategy:'fingerprint' },
  moduleOrdering: { digits:5, defaultOrder:50000, reservedFirstActionId:'core.ui.header-bar', reservedFirstOrder:0, reservedProfileActionId:'core.user.profile', reservedProfileOrder:10, reservedLastActionId:'project.system.update-log', reservedLastOrder:99999, minNonReservedOrder:1 },
  pegboard: { autoFollowLiveDefault:true, liveSwitchDebounceMs:100, userActionPulseMs:900, moduleColumns:3 },
  branding: { productName:'LOOM', visualName:'Pegboard' },
  performance:{registrySessionCache:true,registryRevalidate:true,registryStartupCacheMaxAgeMs:120000,registryFetchTimeoutMs:5000,moduleLoadConcurrency:4,moduleImportTimeoutMs:8000,moduleFactoryTimeoutMs:5000,moduleMountTimeoutMs:7000,moduleActivationTimeoutMs:7000,moduleLayoutHydrateTimeoutMs:2500,replayBatchMs:1800},
  foundations:{guestProfiles:true,identityLineage:true,projectSandbox:true,capabilityContracts:true,migrations:true,health:true,audit:true,replayCapture:true,designSystem:true}
});
window.PegboardEngineConfig = window.LoomConfig;
