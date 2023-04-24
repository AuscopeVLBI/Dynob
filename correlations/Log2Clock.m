[year,doy,hh,mm,ss,fmout]=textread('../tmp/fmout.txt', '%f %f %f %f %f %f');
[a,b,c,d, flaggo]=textread('../tmp/fmoutepoch.txt', '%f %f %f %s %f');
ref=b+c/24;
UT=doy + hh/24 +mm/1440 +ss/86400 - ref;
[A,B]=sort(fmout);
if flaggo==1
  A=polyfit(UT(B(floor(.025*length(B)):ceil(.975*length(B))))*86400, fmout(floor(.025*length(B)):ceil(.975*length(B))), 1);
else
  A=polyfit(86400*UT(~isnan(fmout)), fmout(~isnan(fmout)), 1);
end

if d{1} == 'ww'
  fprintf('\tclockOffset = %1.3e\n\tclockRate = %1.3e\n\tclockEpoch = %4dy%03dd%02dh%02.0fm00s\n', A(2)*1E6, A(1)*1E6, a,b,floor(c),60*(c-floor(c)));
else
  fprintf('\tclockOffset = %1.3e\n\tclockRate = %1.3e\n\tclockEpoch = %4dy%03dd%02dh%02.0fm00s\n', -A(2)*1E6, -A(1)*1E6, a,b,floor(c),60*(c-floor(c)));
end
